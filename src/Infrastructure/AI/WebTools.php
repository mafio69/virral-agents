<?php
declare(strict_types=1);
namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;

final class WebTools
{
    public static function definitions(): array
    {
        return [
            ['name' => 'web_search', 'description' => 'Poproś użytkownika o zgodę na jedno wyszukanie publicznych informacji. Nic nie zostanie wykonane przed Allow. Nie przekazuj danych prywatnych.',
                'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query'], 'additionalProperties' => false]],
            ['name' => 'web_fetch', 'description' => 'Poproś o osobną zgodę na przeczytanie konkretnej publicznej strony. Nie twierdź, że strona jest przeczytana przed otrzymaniem jej treści.',
                'parameters' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string']], 'required' => ['url'], 'additionalProperties' => false]],
        ];
    }

    public static function compat(): array
    {
        return array_map(static fn(array $tool): array => ['type' => 'function', 'function' => $tool], self::definitions());
    }

    public static function request(string $name, mixed $arguments): array
    {
        if (is_string($arguments)) {
            try { $arguments = json_decode($arguments, true, 8, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { throw new AiConnectionException('invalid_response'); }
        }
        $field = match ($name) { 'web_search' => 'query', 'web_fetch' => 'url', default => throw new AiConnectionException('invalid_response') };
        if (!is_array($arguments) || array_keys($arguments) !== [$field] || !is_string($arguments[$field]) || trim($arguments[$field]) === ''
            || strlen($arguments[$field]) > ($field === 'query' ? 1000 : 4096)) throw new AiConnectionException('invalid_response');
        return ['kind' => $field === 'query' ? 'search' : 'fetch', $field => trim($arguments[$field])];
    }
}
