<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\Chat\SearchQuery;
use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    public function testFromArrayNormalizesTokensPhraseAndSites(): void
    {
        $query = SearchQuery::fromArray(['query' => '  Symfony   router ', 'must_have' => [' twig ', '', 'twig', "multi\nword"],
            'exclude' => [' legacy '], 'exact_phrase' => '  konfiguracja   yaml  ', 'sites' => [' SymFony.COM ', 'symfony.com']]);
        self::assertSame('Symfony   router', $query->query);
        self::assertSame(['twig', 'multi word'], $query->mustHave);
        self::assertSame(['legacy'], $query->exclude);
        self::assertSame('konfiguracja yaml', $query->exactPhrase);
        self::assertSame(['symfony.com'], $query->sites);
        self::assertTrue($query->hasFilters());
        self::assertSame(['query' => 'Symfony   router', 'must_have' => ['twig', 'multi word'], 'exclude' => ['legacy'],
            'exact_phrase' => 'konfiguracja yaml', 'sites' => ['symfony.com']], $query->toArray());
    }

    public function testEmptyInputYieldsQueryWithoutFilters(): void
    {
        $query = SearchQuery::fromArray([]);
        self::assertFalse($query->hasFilters());
        self::assertSame(['query' => ''], $query->toArray());
    }

    public function testFromArrayRejectsInvalidShapes(): void
    {
        foreach (['x', null, 1, ['symfony'], [['query' => 'x']],
            ['unknown' => 'x'],
            ['query' => 1],
            ['must_have' => 'twig'],
            ['must_have' => ['twig' => 1]],
            ['must_have' => [1]],
            ['exclude' => ['-word']],
            ['must_have' => ['+word']],
            ['must_have' => ['quo"te']],
            ['must_have' => [str_repeat('a', 65)]],
            ['exclude' => array_fill(0, 11, 'word')],
            ['exact_phrase' => 'a"b'],
            ['exact_phrase' => str_repeat('a', 101)],
            ['exact_phrase' => 1],
            ['sites' => 'example.com'],
            ['sites' => ['https://example.com']],
            ['sites' => ['example.com/path']],
            ['sites' => ['example.com:8080']],
            ['sites' => ['-example.com']],
            ['sites' => array_fill(0, 6, 'example.com')],
            ['query' => str_repeat('a', 1001)],
        ] as $input) {
            try {
                SearchQuery::fromArray($input);
                self::fail('Invalid input must be rejected: '.json_encode($input));
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function testTokensMayNotInjectSearchOperators(): void
    {
        // A leading minus is the exclusion operator; minus inside a token is regular text.
        self::assertSame(['cloud-native'], SearchQuery::fromArray(['must_have' => ['cloud-native']])->mustHave);
        try {
            SearchQuery::fromArray(['must_have' => ['-native']]);
            self::fail('Operator injection must be rejected');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
    }

    public function testConstructorValidatesDirectlyProvidedValues(): void
    {
        try {
            new SearchQuery(exclude: ['-word']);
            self::fail('Constructor must validate tokens');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
    }
}
