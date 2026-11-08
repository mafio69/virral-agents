<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\Chat\SearchQuery;
use App\Dto\Chat\SearchQueryBuilder;
use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class SearchQueryBuilderTest extends TestCase
{
    public function testComposesAllOperatorsInStableOrder(): void
    {
        $query = new SearchQuery(
            'Symfony router',
            ['legacy', 'deprecated'],
            ['twig', 'security'],
            'konfiguracja yaml',
            ['symfony.com', 'twig.symfony.com'],
        );
        self::assertSame(
            'Symfony router +twig +security -legacy -deprecated "konfiguracja yaml" site:symfony.com site:twig.symfony.com',
            SearchQueryBuilder::build($query),
        );
    }

    public function testOmitsMissingParts(): void
    {
        self::assertSame('Symfony', SearchQueryBuilder::build(new SearchQuery('Symfony')));
        self::assertSame('', SearchQueryBuilder::build(new SearchQuery()));
        self::assertSame('Symfony +twig', SearchQueryBuilder::build(new SearchQuery('Symfony', mustHave: ['twig'])));
        self::assertSame('Symfony "exact words"', SearchQueryBuilder::build(new SearchQuery('Symfony', exactPhrase: 'exact words')));
        self::assertSame('site:example.com', SearchQueryBuilder::build(new SearchQuery(sites: ['example.com'])));
    }

    public function testMultiWordTokensStaySingleQuotedTerms(): void
    {
        // A space inside a token must not split it into two operators.
        self::assertSame(
            'Symfony +"twig -legacy" -"old docs"',
            SearchQueryBuilder::build(new SearchQuery('Symfony', exclude: ['old docs'], mustHave: ['twig -legacy'])),
        );
    }

    public function testRejectsQueryLongerThanTheContractLimit(): void
    {
        $longBase = str_repeat('a', 990);
        $query = new SearchQuery($longBase, mustHave: [str_repeat('b', 20)]);
        try {
            SearchQueryBuilder::build($query);
            self::fail('Composed queries above 1000 bytes must be rejected');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
    }
}
