<?php

declare(strict_types=1);

namespace App\Dto\Chat;

use App\Exception\ValidationException;

final readonly class SearchOptions
{
    public function __construct(
        public string $engine = 'off',
        public int $maxCalls = 1,
        public SearchQuery $query = new SearchQuery(),
        public bool $approved = false,
    ) {
        if (!in_array($engine, ['off', 'mcp'], true) || $maxCalls !== 1
            || ($engine === 'mcp' && (!$approved || $this->query->query === ''))
            || ($engine === 'off' && ($approved || $this->query->query !== '' || $this->query->hasFilters()))) {
            throw new ValidationException('Nieprawidłowe ustawienia wyszukiwania.');
        }
    }

    /** Parses and validates the search section of a chat message request. */
    public static function fromInput(mixed $input): self
    {
        if (!is_array($input) || (array_is_list($input) && $input !== [])
            || array_diff(array_keys($input), ['engine', 'max_calls', 'query', 'approved', 'exclude', 'must_have', 'exact_phrase', 'sites']) !== []
            || !is_string($input['engine'] ?? 'off') || !is_int($input['max_calls'] ?? 1)
            || !is_string($input['query'] ?? '') || !is_bool($input['approved'] ?? false)) {
            throw new ValidationException('Nieprawidłowe ustawienia wyszukiwania.');
        }
        $query = SearchQuery::fromArray(array_intersect_key(
            $input,
            ['query' => 0, 'exclude' => 0, 'must_have' => 0, 'exact_phrase' => 0, 'sites' => 0],
        ));
        SearchQueryBuilder::build($query);
        return new self($input['engine'] ?? 'off', $input['max_calls'] ?? 1, $query, $input['approved'] ?? false);
    }
}
