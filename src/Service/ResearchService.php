<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Dto\Chat\SearchQuery;
use App\Dto\Chat\SearchQueryBuilder;
use App\Repository\ChatRepository;
use App\Repository\ResearchRepositoryInterface;
use App\Infrastructure\Search\PublicWebUrl;
use App\Infrastructure\Search\SearchClientInterface;
use App\Infrastructure\Search\SearchException;
use App\Infrastructure\Search\WebPageFetcherInterface;

final readonly class ResearchService implements ResearchServiceInterface
{
    public function __construct(
        private ResearchRepositoryInterface $store,
        private ChatRepository $chats,
        private SearchClientInterface $search,
        private WebPageFetcherInterface $fetcher
    ) {
    }

    public function state(int $userId, int $chatId): array
    {
        $this->authorize($userId, $chatId);
        return $this->publicState($this->store->read($userId, $chatId));
    }

    public function adoptDraft(int $userId, int $chatId): void
    {
        $this->authorize($userId, $chatId);
        $this->store->adoptDraft($userId, $chatId);
    }

    public function propose(int $userId, int $chatId, array $request, string $origin = 'user'): array
    {
        $this->authorize($userId, $chatId);
        $request = $this->validateRequest($request);
        $state = $this->store->change($userId, $chatId, function (array $state) use ($request, $origin): array {
            return $this->queue($state, $request, $origin);
        });
        return $this->publicState($state);
    }

    public function select(int $userId, int $chatId, string $sourceId, bool $selected, string $target = 'source'): array
    {
        $this->authorize($userId, $chatId);
        $state = $this->store->change($userId, $chatId, function (array $state) use ($sourceId, $selected, $target): array {
            return match ($target) {
                'source' => $this->selectSource($state, $sourceId, $selected),
                'list' => $this->selectList($state, $sourceId, $selected),
                'save_list' => $this->saveList($state, $sourceId, $selected),
                'delete_list' => $this->deleteList($state, $sourceId),
                default => throw new ValidationException('Nieznana operacja.'),
            };
        });
        return $this->publicState($state);
    }

    public function prepare(int $userId, int $chatId): array
    {
        $this->authorize($userId, $chatId);
        $state = $this->store->change($userId, $chatId, function (array $state): array {
            foreach ($state['sources'] as $source) {
                if ($source['page']['status'] === 'unread') {
                    $state = $this->queue($state, ['kind' => 'fetch', 'url' => $source['url']], 'user');
                }
            }
            return $state;
        });
        return $this->publicState($state);
    }

    public function clear(int $userId, int $chatId): array
    {
        $this->authorize($userId, $chatId);
        $state = $this->store->change($userId, $chatId, static function (array $state): array {
            $state['results'] = [];
            $state['sources'] = [];
            $state['excluded'] = [];
            $state['lists'] = [];
            $state['pages'] = [];
            $state['actions'] = array_filter(
                $state['actions'] ?? [],
                static fn (array $action): bool => ($action['status'] ?? '') === 'running'
            );
            return $state;
        });
        return $this->publicState($state);
    }

    public function clearEphemeral(int $userId, int $chatId): array
    {
        $this->authorize($userId, $chatId);
        $state = $this->store->change($userId, $chatId, function (array $state): array {
            $removedItems = [];
            foreach ($state['lists'] ?? [] as $listId => $list) {
                if (($list['mode'] ?? 'pool') === 'ephemeral' && empty($list['saved'])) {
                    foreach ($list['items'] ?? [] as $item) {
                        $removedItems[] = $item;
                    }
                    unset($state['lists'][$listId]);
                }
            }
            if ($removedItems !== []) {
                $state = $this->pruneOrphanSources($state, $removedItems);
                $state = $this->syncListSelection($state);
            }
            foreach ($state['actions'] as &$action) {
                if (($action['origin'] ?? '') === 'ai' && !in_array($action['status'] ?? '', ['pending', 'running'], true)) {
                    $action['consumed'] = true;
                }
            }
            unset($action);
            return $state;
        });
        return $this->publicState($state);
    }

    public function copySelectedToDraft(int $userId, int $chatId): array
    {
        $this->authorize($userId, $chatId);
        if ($chatId === 0) {
            return $this->state($userId, 0);
        }
        $current = $this->store->read($userId, $chatId);
        $selectedSources = $current['sources'] ?? [];
        $selectedLists = [];
        foreach ($current['lists'] ?? [] as $listId => $list) {
            $selectedItems = array_values(array_filter(
                $list['items'] ?? [],
                static fn (array $item): bool => isset($selectedSources[$item['id']])
            ));
            if ($selectedItems !== []) {
                $list['items'] = $selectedItems;
                $list['selected'] = true;
                $selectedLists[$listId] = $list;
            }
        }
        $selectedPages = [];
        foreach ($selectedSources as $id => $source) {
            if (($source['page']['status'] ?? '') === 'loaded') {
                $selectedPages[$id] = $source['page'];
            } elseif (isset($current['pages'][$id])) {
                $selectedPages[$id] = $current['pages'][$id];
            }
        }
        $selectedResults = array_values(array_filter(
            $current['results'] ?? [],
            static fn (array $source): bool => isset($selectedSources[$source['id']])
        ));
        $draft = $this->store->change($userId, 0, static function () use ($selectedSources, $selectedLists, $selectedPages, $selectedResults): array {
            return [
                'results' => $selectedResults,
                'sources' => $selectedSources,
                'actions' => [],
                'lists' => $selectedLists,
                'pages' => $selectedPages,
                'excluded' => [],
            ];
        });
        return $this->publicState($draft);
    }

    public function decide(int $userId, int $chatId, string $actionId, bool $allow): array
    {
        $this->authorize($userId, $chatId);
        $state = $this->store->change($userId, $chatId, function (array $state) use ($actionId, $allow): array {
            if (!isset($state['actions'][$actionId])) {
                throw new NotFoundException('Action not found');
            }
            $action = &$state['actions'][$actionId];
            if ($action['status'] !== 'pending') {
                throw new ValidationException('Ta decyzja została już wykorzystana.');
            }
            if ($allow && time() - $action['created'] > 1800) {
                throw new ValidationException('Zgoda wygasła. Wybierz Deny i utwórz nową prośbę.');
            }
            $action['status'] = $allow ? 'running' : 'denied';
            if ($action['request']['kind'] === 'fetch') {
                $url = $action['request']['url'];
                $id = self::sourceId($url);
                if ($allow && !isset($state['sources'][$id])) {
                    if (count($state['sources']) >= 20) {
                        throw new ValidationException('Limit 20 źródeł.');
                    }
                    $state['sources'][$id] = ['id' => $id, 'url' => $url, 'title' => $url, 'snippet' => '', 'page' => ['status' => 'unread']];
                }
                if (!$allow && isset($state['sources'][$id])) {
                    $state['sources'][$id]['page'] = ['status' => 'denied'];
                }
            }
            return $state;
        });
        if (!$allow) {
            return $this->publicState($state);
        }
        $request = $state['actions'][$actionId]['request'];
        $result = null;
        $reason = null;
        try {
            if ($request['kind'] === 'search') {
                try {
                    $query = SearchQuery::fromArray(array_diff_key($request, ['kind' => true, 'mode' => true]));
                    $result = $this->search->search(SearchQueryBuilder::build($query), 10);
                } finally {
                    $this->search->close();
                }
            } else {
                $result = $this->fetcher->fetch($request['url']);
            }
        } catch (SearchException $error) {
            $reason = $error->reason;
        } catch (\Throwable) {
            $reason = 'unavailable';
        }
        $state = $this->store->change($userId, $chatId, function (array $state) use ($actionId, $request, $result, $reason): array {
            $state['actions'][$actionId]['status'] = $reason === null ? 'done' : 'failed';
            if ($reason !== null) {
                $state['actions'][$actionId]['reason'] = $reason;
            }
            if ($request['kind'] === 'search' && $result !== null) {
                $state = $this->applySearchResults($state, $actionId, $request, $result);
            } elseif ($request['kind'] === 'fetch') {
                $id = self::sourceId($request['url']);
                if ($result !== null) {
                    $page = ['status' => 'loaded', 'text' => $result['text'], 'truncated' => $result['truncated'], 'fetched_at' => gmdate('c')];
                    $state['pages'][$id] = $page;
                    // A removed source is not silently reselected by an in-flight request.
                    if (isset($state['sources'][$id])) {
                        $state['sources'][$id]['page'] = $page;
                    }
                } elseif (isset($state['sources'][$id])) {
                    $state['sources'][$id]['page'] = ['status' => 'failed', 'reason' => $reason];
                }
            }
            return $state;
        });
        return $this->publicState($state);
    }

    public function evidence(int $userId, int $chatId): array
    {
        $this->authorize($userId, $chatId);
        $state = $this->store->read($userId, $chatId);
        $evidence = [];
        $budget = 100000;
        foreach ($state['sources'] as $source) {
            $page = $source['page'];
            if ($page['status'] === 'unread') {
                throw new ValidationException('Najpierw zdecyduj o pobraniu zaznaczonych stron.');
            }
            $text = $page['status'] === 'loaded' ? mb_substr($page['text'], 0, max(0, $budget)) : '';
            $budget -= mb_strlen($text);
            // Search title and snippet give minimal context even when the page was not fetched.
            $evidence[] = ['url' => $source['url'], 'title' => mb_substr($source['title'], 0, 300), 'snippet' => mb_substr($source['snippet'] ?? '', 0, 400),
                'status' => $page['status'], 'text' => $text,
                'truncated' => ($page['truncated'] ?? false) || mb_strlen($text) < mb_strlen($page['text'] ?? ''), 'fetched_at' => $page['fetched_at'] ?? null,
                // Closed fetch failure code so the model can prefer another source instead of guessing.
                'reason' => $page['status'] === 'failed' ? ($page['reason'] ?? 'unavailable') : null];
        }
        $decisions = array_filter(
            $state['actions'],
            static fn (array $action): bool => ($action['origin'] ?? '') === 'ai' && empty($action['consumed'])
        );
        return ['sources' => $evidence, 'decisions' => array_values(array_map(static fn (array $action): array => ['request' => $action['request'], 'status' => $action['status']], $decisions))];
    }

    private function authorize(int $userId, int $chatId): void
    {
        if ($userId < 1 || $chatId < 0) {
            throw new NotFoundException('Research not found');
        }
        if ($chatId === 0) {
            return;
        }
        $chat = $this->chats->findById($chatId);
        if (!$chat || (int)$chat['user_id'] !== $userId) {
            throw new NotFoundException('Research not found');
        }
    }

    private function validateRequest(array $request): array
    {
        $kind = $request['kind'] ?? null;
        if ($kind === 'search' && array_diff(array_keys($request), ['kind', 'query', 'exclude', 'must_have', 'exact_phrase', 'sites', 'mode']) === []) {
            $hasMode = array_key_exists('mode', $request);
            $mode = $request['mode'] ?? null;
            if ($hasMode && (!is_string($mode) || !in_array($mode, ['pool', 'ephemeral'], true))) {
                throw new ValidationException('Nieprawidłowa prośba WWW.');
            }
            $query = SearchQuery::fromArray(array_diff_key($request, ['kind' => true, 'mode' => true]));
            if ($query->query === '') {
                throw new ValidationException('Nieprawidłowa prośba WWW.');
            }
            SearchQueryBuilder::build($query);
            $validated = ['kind' => 'search', ...$query->toArray()];
            if ($hasMode) {
                $validated['mode'] = $mode;
            }
            return $validated;
        }
        if ($kind === 'fetch' && array_diff(array_keys($request), ['kind', 'url']) === [] && is_string($request['url'] ?? null)) {
            return ['kind' => 'fetch', 'url' => PublicWebUrl::normalize($request['url'])];
        }
        throw new ValidationException('Nieprawidłowa prośba WWW.');
    }

    private function queue(array $state, array $request, string $origin): array
    {
        foreach ($state['actions'] as $action) {
            if ($action['request'] === $request && in_array($action['status'], ['pending', 'running'], true)) {
                return $state;
            }
            if ($origin === 'ai' && $action['request'] === $request && $action['status'] === 'denied') {
                return $state;
            }
        }
        $pending = array_filter($state['actions'], static fn (array $action): bool => in_array($action['status'], ['pending', 'running'], true));
        if (count($pending) >= 20) {
            throw new ValidationException('Najpierw rozstrzygnij oczekujące prośby.');
        }
        if (count($state['actions']) >= 80) {
            foreach ($state['actions'] as $id => $action) {
                if (!in_array($action['status'], ['pending', 'running'], true)) {
                    unset($state['actions'][$id]);
                    break;
                }
            }
        }
        $id = bin2hex(random_bytes(16));
        $state['actions'][$id] = ['id' => $id, 'request' => $request, 'origin' => $origin === 'ai' ? 'ai' : 'user', 'status' => 'pending', 'created' => time()];
        return $state;
    }

    private function applySearchResults(array $state, string $actionId, array $request, array $result): array
    {
        $state['excluded'] = [];
        $state['lists'] ??= [];
        $state['pages'] ??= [];
        foreach ($state['sources'] as $id => $source) {
            if (($source['page']['status'] ?? '') === 'loaded') {
                $state['pages'][$id] = $source['page'];
            }
        }
        $newResults = [];
        foreach ($result['results'] as $entry) {
            try {
                $url = PublicWebUrl::normalize($entry['url']);
            } catch (ValidationException) {
                continue;
            }
            $id = self::sourceId($url);
            $newResults[$id] = [
                'id' => $id,
                'url' => $url,
                'title' => mb_substr($entry['title'], 0, 300),
                'snippet' => mb_substr($entry['snippet'], 0, 400),
            ];
        }
        $mode = ($request['mode'] ?? 'pool') === 'ephemeral' ? 'ephemeral' : 'pool';
        $state['lists'][$actionId] = [
            'id' => $actionId,
            'query' => $request['query'],
            'mode' => $mode,
            'saved' => $mode === 'pool',
            'selected' => true,
            'created' => time(),
            'items' => array_values($newResults),
        ];
        $state['results'] = array_values($newResults);
        $newSources = [];
        foreach ($newResults as $id => $source) {
            if (count($newSources) >= 20) {
                break;
            }
            $page = $state['sources'][$id]['page'] ?? $state['pages'][$id] ?? ['status' => 'unread'];
            $newSources[$id] = $source + ['page' => $page];
        }
        $existing = array_diff_key($state['sources'], $newSources);
        $keepCount = max(0, 20 - count($newSources));
        $kept = $keepCount > 0 ? array_slice($existing, -$keepCount, null, true) : [];
        foreach (array_keys(array_diff_key($existing, $kept)) as $droppedId) {
            $state = $this->cancelPendingFetch($state, $droppedId);
        }
        $state['sources'] = $kept + $newSources;
        return $this->syncListSelection($state);
    }

    private function selectSource(array $state, string $sourceId, bool $selected): array
    {
        if (!$selected) {
            $state = $this->deselectSource($state, $sourceId);
            $state['excluded'][$sourceId] = true;
            $state['excluded'] = array_slice($state['excluded'], -80, null, true);
            return $this->syncListSelection($state);
        }
        unset($state['excluded'][$sourceId]);
        if (isset($state['sources'][$sourceId])) {
            return $this->syncListSelection($state);
        }
        if (count($state['sources']) >= 20) {
            throw new ValidationException('Można wybrać maksymalnie 20 źródeł.');
        }
        $source = $this->findSourceById($state, $sourceId);
        if ($source === null) {
            throw new NotFoundException('Source not found');
        }
        $page = $state['pages'][$sourceId] ?? ['status' => 'unread'];
        $state['sources'][$sourceId] = $source + ['page' => $page];
        return $this->syncListSelection($state);
    }

    private function selectList(array $state, string $listId, bool $selected): array
    {
        if (!isset($state['lists'][$listId])) {
            throw new NotFoundException('List not found');
        }
        if (!$selected) {
            $state['lists'][$listId]['selected'] = false;
            $protected = [];
            foreach ($state['lists'] as $otherId => $otherList) {
                if ($otherId === $listId || empty($otherList['selected'])) {
                    continue;
                }
                foreach ($otherList['items'] ?? [] as $item) {
                    $protected[$item['id']] = true;
                }
            }
            foreach ($state['lists'][$listId]['items'] ?? [] as $item) {
                if (!isset($protected[$item['id']])) {
                    $state = $this->deselectSource($state, $item['id']);
                }
            }
            return $this->syncListSelection($state);
        }
        $items = $state['lists'][$listId]['items'] ?? [];
        foreach ($items as $item) {
            $id = $item['id'];
            if (isset($state['sources'][$id])) {
                continue;
            }
            if (count($state['sources']) >= 20) {
                break;
            }
            unset($state['excluded'][$id]);
            $page = $state['pages'][$id] ?? ['status' => 'unread'];
            $state['sources'][$id] = $item + ['page' => $page];
        }
        $hasSelected = false;
        foreach ($items as $item) {
            if (isset($state['sources'][$item['id']])) {
                $hasSelected = true;
                break;
            }
        }
        if ($items !== [] && !$hasSelected && count($state['sources']) >= 20) {
            throw new ValidationException('Można wybrać maksymalnie 20 źródeł.');
        }
        return $this->syncListSelection($state);
    }

    private function saveList(array $state, string $listId, bool $saved): array
    {
        if (!isset($state['lists'][$listId])) {
            throw new NotFoundException('List not found');
        }
        $state['lists'][$listId]['saved'] = $saved;
        if ($saved) {
            $state['lists'][$listId]['mode'] = 'pool';
        }
        return $state;
    }

    private function deleteList(array $state, string $listId): array
    {
        if (!isset($state['lists'][$listId])) {
            throw new NotFoundException('List not found');
        }
        $items = $state['lists'][$listId]['items'] ?? [];
        unset($state['lists'][$listId]);
        $state = $this->pruneOrphanSources($state, $items);
        return $this->syncListSelection($state);
    }

    private function pruneOrphanSources(array $state, array $candidateItems): array
    {
        $keepIds = [];
        foreach ($state['lists'] ?? [] as $list) {
            foreach ($list['items'] ?? [] as $item) {
                $keepIds[$item['id']] = true;
            }
        }
        $removeIds = [];
        foreach ($candidateItems as $item) {
            $id = $item['id'];
            if (!isset($keepIds[$id])) {
                $removeIds[$id] = true;
                $state = $this->deselectSource($state, $id);
                unset($state['pages'][$id]);
            }
        }
        if ($removeIds !== []) {
            $state['results'] = array_values(array_filter(
                $state['results'] ?? [],
                static fn (array $source): bool => !isset($removeIds[$source['id']])
            ));
        }
        return $state;
    }

    private function deselectSource(array $state, string $sourceId): array
    {
        if (($state['sources'][$sourceId]['page']['status'] ?? '') === 'loaded') {
            $state['pages'][$sourceId] = $state['sources'][$sourceId]['page'];
        }
        unset($state['sources'][$sourceId]);
        return $this->cancelPendingFetch($state, $sourceId);
    }

    private function cancelPendingFetch(array $state, string $sourceId): array
    {
        foreach ($state['actions'] as &$action) {
            if ($action['status'] === 'pending' && ($action['request']['kind'] ?? '') === 'fetch'
                && self::sourceId($action['request']['url']) === $sourceId) {
                $action['status'] = 'cancelled';
            }
        }
        unset($action);
        return $state;
    }

    private function findSourceById(array $state, string $sourceId): ?array
    {
        foreach ($state['results'] ?? [] as $source) {
            if ($source['id'] === $sourceId) {
                return $source;
            }
        }
        foreach (array_reverse($state['lists'] ?? []) as $list) {
            foreach ($list['items'] ?? [] as $source) {
                if ($source['id'] === $sourceId) {
                    return $source;
                }
            }
        }
        return null;
    }

    private function syncListSelection(array $state): array
    {
        foreach ($state['lists'] ?? [] as $listId => $list) {
            $anySelected = false;
            foreach ($list['items'] ?? [] as $item) {
                if (isset($state['sources'][$item['id']])) {
                    $anySelected = true;
                    break;
                }
            }
            $state['lists'][$listId]['selected'] = $anySelected;
        }
        return $state;
    }

    private function publicState(array $state): array
    {
        $sources = [];
        foreach ($state['sources'] as $source) {
            unset($source['page']['text']);
            $sources[] = $source;
        }
        $results = array_map(static fn (array $source): array => $source + ['selected' => isset($state['sources'][$source['id']])], $state['results']);
        $lists = [];
        foreach (array_reverse(array_values($state['lists'] ?? [])) as $list) {
            $items = [];
            $anySelected = false;
            foreach ($list['items'] ?? [] as $item) {
                $id = $item['id'];
                $isSelected = isset($state['sources'][$id]);
                if ($isSelected) {
                    $anySelected = true;
                }
                $page = $state['sources'][$id]['page'] ?? $state['pages'][$id] ?? ['status' => 'unread'];
                unset($page['text']);
                $items[] = $item + ['selected' => $isSelected, 'page' => $page];
            }
            $list['items'] = $items;
            $list['selected'] = $anySelected;
            $lists[] = $list;
        }
        return ['results' => $results, 'sources' => $sources, 'actions' => array_values($state['actions']), 'lists' => $lists];
    }

    private static function sourceId(string $url): string
    {
        return hash('sha256', $url);
    }
}
