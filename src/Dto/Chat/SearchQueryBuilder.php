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
    /** Composes the validated query into one engine string; multi-word tokens stay quoted terms. */
    public static function build(SearchQuery $query): string
    {
        $parts = [$query->query];
        foreach ($query->mustHave as $token) {
            // Whitespace inside a token would split it into separate terms (operator injection).
            $parts[] = '+'.(str_contains($token, ' ') ? '"'.$token.'"' : $token);
        }
        foreach ($query->exclude as $token) {
            $parts[] = '-'.(str_contains($token, ' ') ? '"'.$token.'"' : $token);
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
