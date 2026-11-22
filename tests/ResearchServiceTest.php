<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\ValidationException;
use App\Infrastructure\Search\SearchClientInterface;
use App\Infrastructure\Search\WebPageFetcherInterface;
use App\Repository\ResearchRepository;
use App\Service\ResearchService;

final class ResearchServiceTest extends ChatServiceTestCase
{
    private function results(string $prefix, int $count): array
    {
        return ['source' => 'searxng', 'results' => array_map(static fn (int $i): array => ['title' => $prefix.$i, 'url' => 'https://example.org/'.$prefix.$i, 'snippet' => 'Fixture snippet'], range(1, $count))];
    }
    private function service(SearchClientInterface $search, WebPageFetcherInterface $fetcher): ResearchService
    {
        return new ResearchService(new ResearchRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo)), $this->chatRepository, $search, $fetcher);
    }
    private function pending(array $state): array
    {
        return array_values(array_filter($state['actions'], static fn (array $a): bool => $a['status'] === 'pending'));
    }

    public function testThreePlusTwoSourcesSurviveSearchRefreshDeduplicationAndDraftAdoption(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::exactly(2))->method('search')->willReturnOnConsecutiveCalls($this->results('first', 4), $this->results('next', 3));
        $fetcher = $this->createMock(WebPageFetcherInterface::class);
        $fetcher->expects(self::never())->method('fetch');
        $service = $this->service($search, $fetcher);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'First public query']);
        self::assertSame([], $state['results']);
        $state = $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        $service->select($this->userId, 0, $state['results'][3]['id'], false);
        self::assertCount(3, $service->state($this->userId, 0)['sources']);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'Next public query']);
        $state = $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        $service->select($this->userId, 0, $state['results'][2]['id'], false);
        $state = $service->state($this->userId, 0);
        self::assertCount(5, $state['sources']);
        self::assertCount(3, $state['results']);
        self::assertCount(2, $state['lists']);
        self::assertSame('https://example.org/first1', $state['sources'][0]['url']);
        $chat = $this->chatRepository->create($this->userId, 'Fixture research');
        $service->adoptDraft($this->userId, $chat);
        self::assertCount(5, $service->state($this->userId, $chat)['sources']);
        self::assertSame([], $service->state($this->userId, 0)['sources']);
        $stored = $this->pdo->query('SELECT payload FROM research_workspaces')->fetchColumn();
        self::assertStringNotContainsString('example.org', $stored);
        self::assertStringNotContainsString('Fixture snippet', $stored);
        self::assertStringNotContainsString('public query', $stored);
    }

    public function testDenyDoesNothingAndDecisionCannotBeReplayed(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::never())->method('search');
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $fetch->expects(self::never())->method('fetch');
        $service = $this->service($search, $fetch);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'Public query']);
        $id = $state['actions'][0]['id'];
        $state = $service->decide($this->userId, 0, $id, false);
        self::assertSame('denied', $state['actions'][0]['status']);
        $this->expectException(ValidationException::class);
        $service->decide($this->userId, 0, $id, true);
    }

    public function testFetchNeedsSeparateApprovalAndOnlySelectedReadablePagesBecomeEvidence(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->method('search')->willReturn($this->results('page', 3));
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $fetch->expects(self::once())->method('fetch')->with('https://example.org/page1')->willReturn(['url' => 'https://example.org/page1', 'text' => 'Actually fetched fixture text', 'truncated' => false]);
        $service = $this->service($search, $fetch);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'Public query']);
        $state = $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        $service->select($this->userId, 0, $state['results'][2]['id'], false);
        foreach (array_slice($state['results'], 0, 2) as $source) {
            $service->select($this->userId, 0, $source['id'], true);
        }
        $state = $service->prepare($this->userId, 0);
        $pending = $this->pending($state);
        self::assertCount(2, $pending);
        $service->decide($this->userId, 0, $pending[0]['id'], true);
        $state = $service->decide($this->userId, 0, $pending[1]['id'], false);
        self::assertArrayNotHasKey('text', $state['sources'][0]['page']);
        $evidence = $service->evidence($this->userId, 0);
        self::assertSame('Actually fetched fixture text', $evidence['sources'][0]['text']);
        self::assertSame('denied', $evidence['sources'][1]['status']);
        self::assertSame('', $evidence['sources'][1]['text']);
        self::assertSame('page2', $evidence['sources'][1]['title']);
        self::assertSame('Fixture snippet', $evidence['sources'][1]['snippet']);
        self::assertSame('Fixture snippet', $evidence['sources'][0]['snippet']);
        self::assertSame([], $evidence['decisions']);
        self::assertStringNotContainsString('page3', json_encode($evidence));
        self::assertStringNotContainsString('Actually fetched fixture text', $this->pdo->query('SELECT payload FROM research_workspaces')->fetchColumn());
        $this->expectException(ValidationException::class);
        $service->decide($this->userId, 0, $pending[0]['id'], true);
    }

    public function testFailedFetchKeepsClosedReasonForUserAndModel(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->method('search')->willReturn($this->results('page', 1));
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $fetch->method('fetch')->willThrowException(new \App\Infrastructure\Search\SearchException('http_blocked', 'fetch'));
        $service = $this->service($search, $fetch);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'Public query']);
        $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        $state = $service->decide($this->userId, 0, $this->pending($service->prepare($this->userId, 0))[0]['id'], true);
        self::assertSame(['status' => 'failed', 'reason' => 'http_blocked'], $state['sources'][0]['page']);
        $evidence = $service->evidence($this->userId, 0);
        self::assertSame('failed', $evidence['sources'][0]['status']);
        self::assertSame('http_blocked', $evidence['sources'][0]['reason']);
        self::assertSame('', $evidence['sources'][0]['text']);
        self::assertSame('page1', $evidence['sources'][0]['title']);
        self::assertSame('Fixture snippet', $evidence['sources'][0]['snippet']);
    }

    public function testEvidenceSnippetIsCappedAndEmptyForDirectFetch(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->method('search')->willReturn(['source' => 'searxng', 'results' => [['title' => str_repeat('T', 350), 'url' => 'https://example.org/long', 'snippet' => str_repeat('ś', 450)]]]);
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $fetch->method('fetch')->willThrowException(new \App\Infrastructure\Search\SearchException('timeout', 'fetch'));
        $service = $this->service($search, $fetch);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'Public query']);
        $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        $service->decide($this->userId, 0, $this->pending($service->prepare($this->userId, 0))[0]['id'], false);
        $state = $service->propose($this->userId, 0, ['kind' => 'fetch', 'url' => 'https://example.org/direct']);
        $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        $evidence = $service->evidence($this->userId, 0);
        self::assertSame('denied', $evidence['sources'][0]['status']);
        self::assertSame(300, mb_strlen($evidence['sources'][0]['title']));
        self::assertSame(str_repeat('ś', 400), $evidence['sources'][0]['snippet']);
        self::assertSame('failed', $evidence['sources'][1]['status']);
        self::assertSame('', $evidence['sources'][1]['snippet']);
    }

    public function testAiProposalsCannotExecuteBeforeUserDecisionOrOverrideDeny(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::never())->method('search');
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $fetch->expects(self::never())->method('fetch');
        $service = $this->service($search, $fetch);
        $request = ['kind' => 'search', 'query' => 'AI public query'];
        $state = $service->propose($this->userId, 0, $request, 'ai');
        self::assertSame('ai', $state['actions'][0]['origin']);
        $service->decide($this->userId, 0, $state['actions'][0]['id'], false);
        self::assertSame([], $this->pending($service->propose($this->userId, 0, $request, 'ai')));
        self::assertSame([], $service->state($this->userId + 1, 0)['actions']);
    }

    public function testOtherUserCannotReadOrMutateChatSources(): void
    {
        $chat = $this->chatRepository->create($this->userId, 'Fixture');
        $service = $this->service($this->createMock(SearchClientInterface::class), $this->createMock(WebPageFetcherInterface::class));
        $this->expectException(\App\Exception\NotFoundException::class);
        $service->propose($this->userId + 1, $chat, ['kind' => 'search', 'query' => 'Public']);
    }
    public function testNewSearchResultsAreSelectedWithoutFetchingAndNextSearchStartsWithCleanSlate(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->method('search')->willReturn($this->results('page', 3));
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $fetch->expects(self::never())->method('fetch');
        $service = $this->service($search, $fetch);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'AI question'], 'ai');
        self::assertCount(0, $state['sources']);
        $state = $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        self::assertCount(3, $state['sources']);
        self::assertSame([true, true, true], array_column($state['results'], 'selected'));
        self::assertSame(['unread', 'unread', 'unread'], array_column(array_column($state['sources'], 'page'), 'status'));
        self::assertCount(0, $this->pending($state));
        $excluded = $state['results'][0]['id'];
        $state = $service->select($this->userId, 0, $excluded, false);
        self::assertCount(2, $state['sources']);
        self::assertFalse($state['results'][0]['selected']);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'Repeat query']);
        $state = $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        self::assertCount(3, $state['sources']);
        self::assertTrue($state['results'][0]['selected']);
    }

    public function testAutomaticSelectionRespectsTwentySourceLimitAndAppendsNextSearch(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->method('search')->willReturnOnConsecutiveCalls(
            $this->results('first', 25),
            $this->results('second', 10)
        );
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $fetch->expects(self::never())->method('fetch');
        $service = $this->service($search, $fetch);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'First']);
        $state = $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        self::assertCount(20, $state['sources']);
        self::assertSame('https://example.org/first1', $state['sources'][0]['url']);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'Second']);
        $state = $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        self::assertCount(20, $state['sources']);
        self::assertCount(2, $state['lists']);
        $urls = array_column($state['sources'], 'url');
        self::assertContains('https://example.org/second1', $urls);
        self::assertContains('https://example.org/second10', $urls);
    }

    public function testClearRemovesResultsSourcesExclusionsListsAndPendingActions(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->method('search')->willReturn($this->results('page', 3));
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $service = $this->service($search, $fetch);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'First query']);
        $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        $service->prepare($this->userId, 0);
        $cleared = $service->clear($this->userId, 0);
        self::assertSame([], $cleared['results']);
        self::assertSame([], $cleared['sources']);
        self::assertSame([], $cleared['lists']);
        self::assertSame([], $cleared['actions']);
    }

    public function testListToggleSaveDeleteAndClearEphemeralPreserveLoadedPages(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->method('search')->willReturnOnConsecutiveCalls(
            $this->results('pool', 2),
            $this->results('tmp', 2),
            $this->results('saved', 2)
        );
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $fetch->expects(self::once())->method('fetch')->with('https://example.org/pool1')
            ->willReturn(['url' => 'https://example.org/pool1', 'text' => 'Cached pool text', 'truncated' => false]);
        $service = $this->service($search, $fetch);

        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'Pool query']);
        $poolListId = $this->pending($state)[0]['id'];
        $service->decide($this->userId, 0, $poolListId, true);
        $prepared = $service->prepare($this->userId, 0);
        $pendingFetches = $this->pending($prepared);
        $service->decide($this->userId, 0, $pendingFetches[0]['id'], true);
        $service->decide($this->userId, 0, $pendingFetches[1]['id'], false);

        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'Temp query', 'mode' => 'ephemeral']);
        $tmpListId = $this->pending($state)[0]['id'];
        $state = $service->decide($this->userId, 0, $tmpListId, true);
        self::assertCount(2, $state['lists']);
        self::assertCount(4, $state['sources']);

        $state = $service->select($this->userId, 0, $poolListId, false, 'list');
        self::assertCount(2, $state['sources']);
        self::assertFalse($state['lists'][1]['selected']);

        $state = $service->select($this->userId, 0, $poolListId, true, 'list');
        self::assertCount(4, $state['sources']);
        $pool1 = array_values(array_filter($state['sources'], static fn (array $s): bool => $s['url'] === 'https://example.org/pool1'))[0];
        self::assertSame('loaded', $pool1['page']['status']);

        $afterReply = $service->clearEphemeral($this->userId, 0);
        self::assertCount(1, $afterReply['lists']);
        self::assertSame($poolListId, $afterReply['lists'][0]['id']);
        self::assertCount(2, $afterReply['sources']);

        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => 'Saved temp', 'mode' => 'ephemeral']);
        $savedListId = $this->pending($state)[0]['id'];
        $service->decide($this->userId, 0, $savedListId, true);
        $service->select($this->userId, 0, $savedListId, true, 'save_list');
        $afterReply2 = $service->clearEphemeral($this->userId, 0);
        self::assertCount(2, $afterReply2['lists']);

        $afterTrash = $service->select($this->userId, 0, $savedListId, false, 'delete_list');
        self::assertCount(1, $afterTrash['lists']);
        self::assertCount(2, $afterTrash['sources']);
    }

    public function testCopySelectedToDraftCarriesOnlySelectedSourcesAndLoadedPagesToNewSession(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->method('search')->willReturn($this->results('carry', 3));
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $fetch->expects(self::once())->method('fetch')->with('https://example.org/carry1')
            ->willReturn(['url' => 'https://example.org/carry1', 'text' => 'Carried page body', 'truncated' => false]);
        $service = $this->service($search, $fetch);

        $chatId = $this->chatRepository->create($this->userId, 'Existing chat');
        $state = $service->propose($this->userId, $chatId, ['kind' => 'search', 'query' => 'Carry query']);
        $state = $service->decide($this->userId, $chatId, $this->pending($state)[0]['id'], true);
        $service->select($this->userId, $chatId, $state['results'][2]['id'], false);
        $pending = $this->pending($service->prepare($this->userId, $chatId));
        $service->decide($this->userId, $chatId, $pending[0]['id'], true);
        $service->decide($this->userId, $chatId, $pending[1]['id'], false);

        $draft = $service->copySelectedToDraft($this->userId, $chatId);
        self::assertCount(2, $draft['sources']);
        self::assertCount(1, $draft['lists']);
        self::assertCount(2, $draft['lists'][0]['items']);
        self::assertSame([], $draft['actions']);
        $evidence = $service->evidence($this->userId, 0);
        self::assertSame('Carried page body', $evidence['sources'][0]['text']);
        self::assertCount(2, $service->state($this->userId, $chatId)['sources']);
    }

    public function testSearchRequestKeepsFiltersAndExecutesComposedQuery(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::once())->method('search')
            ->with('PHP router +twig -legacy "kontroler yaml" site:symfony.com', 10)
            ->willReturn($this->results('filtered', 2));
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $service = $this->service($search, $fetch);
        $state = $service->propose($this->userId, 0, ['kind' => 'search', 'query' => ' PHP router ', 'must_have' => ['twig'],
            'exclude' => ['legacy'], 'exact_phrase' => 'kontroler yaml', 'sites' => ['symfony.com']]);
        self::assertSame(
            ['kind' => 'search', 'query' => 'PHP router', 'must_have' => ['twig'],
            'exclude' => ['legacy'], 'exact_phrase' => 'kontroler yaml', 'sites' => ['symfony.com']],
            $this->pending($state)[0]['request']
        );
        $state = $service->decide($this->userId, 0, $this->pending($state)[0]['id'], true);
        self::assertCount(2, $state['results']);
    }

    public function testSearchRequestRejectsInvalidFilters(): void
    {
        $search = $this->createMock(SearchClientInterface::class);
        $search->expects(self::never())->method('search');
        $fetch = $this->createMock(WebPageFetcherInterface::class);
        $service = $this->service($search, $fetch);
        foreach ([
            ['kind' => 'search', 'query' => ''],
            ['kind' => 'search', 'query' => '   '],
            ['kind' => 'search', 'query' => 'Public', 'must_have' => ['-nope']],
            ['kind' => 'search', 'query' => 'Public', 'sites' => ['example.com/path']],
            ['kind' => 'search', 'query' => 'Public', 'unknown' => 'x'],
            ['kind' => 'fetch', 'url' => 'https://example.org/x', 'must_have' => ['y']],
        ] as $request) {
            try {
                $service->propose($this->userId, 0, $request);
                self::fail('Invalid search request must be rejected: '.json_encode($request));
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }
}
