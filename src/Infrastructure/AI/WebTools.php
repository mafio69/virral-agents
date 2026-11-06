<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\Chat\SearchQuery;
use App\Dto\Chat\SearchQueryBuilder;
use App\Exception\AiConnectionException;
use App\Exception\ValidationException;

final class WebTools
{
    public static function definitions(): array
    {
        return [
            ['name' => 'web_search', 'description' => 'Poproś użytkownika o zgodę na jedno wyszukanie publicznych informacji. Nic nie zostanie wykonane przed Allow. Możesz zawęzić wyniki: słowa wymagane (must_have), wykluczone (exclude), dokładna fraza (exact_phrase) i domeny (sites). Nie przekazuj danych prywatnych.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Zapytanie podstawowe.'],
                    'must_have' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 10, 'description' => 'Słowa, które muszą wystąpić w wynikach.'],
                    'exclude' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 10, 'description' => 'Słowa, które nie mogą wystąpić w wynikach.'],
                    'exact_phrase' => ['type' => 'string', 'description' => 'Dokładna fraza, jaka ma wystąpić w wynikach.'],
                    'sites' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 5, 'description' => 'Ograniczenie wyników do domen, np. wikipedia.org.'],
                ], 'required' => ['query'], 'additionalProperties' => false]],
            ['name' => 'web_fetch', 'description' => 'Poproś o osobną zgodę na przeczytanie konkretnej publicznej strony. Nie twierdź, że strona jest przeczytana przed otrzymaniem jej treści.',
                'parameters' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string']], 'required' => ['url'], 'additionalProperties' => false]],
        ];
    }

    public static function compat(): array
    {
        return array_map(static fn (array $tool): array => ['type' => 'function', 'function' => $tool], self::definitions());
    }

    public static function request(string $name, mixed $arguments): array
    {
        if (is_string($arguments)) {
            try {
                $arguments = json_decode($arguments, true, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new AiConnectionException('invalid_response');
            }
        }
        if ($name === 'web_search') {
            if (!is_array($arguments) || !is_string($arguments['query'] ?? null)
                || array_diff(array_keys($arguments), ['query', 'must_have', 'exclude', 'exact_phrase', 'sites']) !== []) {
                throw new AiConnectionException('invalid_response');
            }
            try {
                $query = SearchQuery::fromArray($arguments);
                SearchQueryBuilder::build($query);
            } catch (ValidationException) {
                throw new AiConnectionException('invalid_response');
            }
            return ['kind' => 'search', ...$query->toArray()];
        }
        if ($name === 'web_fetch' && is_array($arguments) && array_keys($arguments) === ['url']
            && is_string($arguments['url']) && trim($arguments['url']) !== '' && strlen($arguments['url']) <= 4096) {
            return ['kind' => 'fetch', 'url' => trim($arguments['url'])];
        }
        throw new AiConnectionException('invalid_response');
    }
}
