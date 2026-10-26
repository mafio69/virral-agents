<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Exception\ValidationException;
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

    public function select(int $userId, int $chatId, string $sourceId, bool $selected): array
    {
        $this->authorize($userId, $chatId);
        $state = $this->store->change($userId, $chatId, function (array $state) use ($sourceId, $selected): array {
            if (!$selected) {
                unset($state['sources'][$sourceId]);
                $state['excluded'][$sourceId] = true;
                $state['excluded'] = array_slice($state['excluded'], -80, null, true);
                foreach ($state['actions'] as &$action) {
                    if ($action['status'] === 'pending' && ($action['request']['kind'] ?? '') === 'fetch'
                        && self::sourceId($action['request']['url']) === $sourceId) {
                        $action['status'] = 'cancelled';
                    }
                }
                return $state;
            }
            unset($state['excluded'][$sourceId]);
            if (isset($state['sources'][$sourceId])) {
                return $state;
            }
            if (count($state['sources']) >= 20) {
                throw new ValidationException('Można wybrać maksymalnie 20 źródeł.');
            }
            foreach ($state['results'] as $source) {
                if ($source['id'] === $sourceId) {
                    $state['sources'][$sourceId] = $source + ['page' => ['status' => 'unread']];
                    return $state;
                }
            }
            throw new NotFoundException('Source not found');
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
            $state['actions'] = array_filter(
                $state['actions'] ?? [],
                static fn (array $action): bool => ($action['status'] ?? '') === 'running'
            );
            return $state;
        });
        return $this->publicState($state);
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
                    $result = $this->search->search($request['query'], 10);
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
                $state['results'] = [];
                $state['sources'] = [];
                $state['excluded'] = [];
                $state['actions'] = [$actionId => $state['actions'][$actionId]];
                foreach ($result['results'] as $entry) {
                    try {
                        $url = PublicWebUrl::normalize($entry['url']);
                    } catch (ValidationException) {
                        continue;
                    }
                    $id = self::sourceId($url);
                    $state['results'][$id] = ['id' => $id, 'url' => $url, 'title' => mb_substr($entry['title'], 0, 300), 'snippet' => mb_substr($entry['snippet'], 0, 400)];
                }
                foreach ($state['results'] as $id => $source) {
                    if (count($state['sources']) >= 20) {
                        break;
                    }
                    $state['sources'][$id] = $source + ['page' => ['status' => 'unread']];
                }
                $state['results'] = array_values($state['results']);
            } elseif ($request['kind'] === 'fetch') {
                $id = self::sourceId($request['url']);
                // A removed source is not silently reselected by an in-flight request.
                if (isset($state['sources'][$id])) {
                    $state['sources'][$id]['page'] = $result === null ? ['status' => 'failed', 'reason' => $reason]
                        : ['status' => 'loaded', 'text' => $result['text'], 'truncated' => $result['truncated'], 'fetched_at' => gmdate('c')];
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
        $decisions = array_filter($state['actions'], static fn (array $action): bool => $action['origin'] === 'ai');
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
        if ($kind === 'search' && array_diff(array_keys($request), ['kind', 'query']) === []
            && is_string($request['query'] ?? null) && trim($request['query']) !== '' && strlen($request['query']) <= 1000) {
            return ['kind' => 'search', 'query' => trim($request['query'])];
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

    private function publicState(array $state): array
    {
        $sources = [];
        foreach ($state['sources'] as $source) {
            unset($source['page']['text']);
            $sources[] = $source;
        }
        $results = array_map(static fn (array $source): array => $source + ['selected' => isset($state['sources'][$source['id']])], $state['results']);
        return ['results' => $results, 'sources' => $sources, 'actions' => array_values($state['actions'])];
    }

    private static function sourceId(string $url): string
    {
        return hash('sha256', $url);
    }
}
