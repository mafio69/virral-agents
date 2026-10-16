<?php

declare(strict_types=1);

namespace App\Dto\Chat;

use App\Exception\ValidationException;

final readonly class SearchOptions
{
    public function __construct(
        public string $engine = 'off',
        public int $maxCalls = 1,
        public string $query = '',
        public bool $approved = false,
    )
    {
        if (!in_array($engine, ['off', 'mcp'], true) || $maxCalls !== 1
            || ($engine === 'mcp' && (!$approved || trim($query) === '' || strlen($query) > 1000))
            || ($engine === 'off' && ($approved || $query !== ''))) {
            throw new ValidationException('Nieprawidłowe ustawienia wyszukiwania.');
        }
    }

    public static function fromInput(mixed $input): self
    {
        if (!is_array($input) || array_is_list($input) && $input !== []
            || array_diff(array_keys($input), ['engine', 'max_calls', 'query', 'approved']) !== []
            || !is_string($input['engine'] ?? 'off') || !is_int($input['max_calls'] ?? 1)
            || !is_string($input['query'] ?? '') || !is_bool($input['approved'] ?? false)) {
            throw new ValidationException('Nieprawidłowe ustawienia wyszukiwania.');
        }
        return new self($input['engine'] ?? 'off', $input['max_calls'] ?? 1, trim($input['query'] ?? ''), $input['approved'] ?? false);
    }
}
