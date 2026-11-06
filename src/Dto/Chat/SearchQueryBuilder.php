<?php

declare(strict_types=1);

namespace App\Dto\Chat;

use App\Exception\ValidationException;

/**
 * Composes a SearchQuery into one search-engine query string:
 * query +required -excluded "exact phrase" site:domain.
 */
final class SearchQueryBuilder
{
    public static function build(SearchQuery $query): string
    {
        $parts = [$query->query];
        foreach ($query->mustHave as $token) {
            $parts[] = '+'.$token;
        }
        foreach ($query->exclude as $token) {
            $parts[] = '-'.$token;
        }
        if ($query->exactPhrase !== '') {
            $parts[] = '"'.$query->exactPhrase.'"';
        }
        foreach ($query->sites as $site) {
            $parts[] = 'site:'.$site;
        }
        $composed = trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
        if (strlen($composed) > SearchQuery::MAX_QUERY_BYTES) {
            throw new ValidationException('Zapytanie wyszukiwania jest zbyt długie.');
        }
        return $composed;
    }
}
