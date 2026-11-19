<?php

declare(strict_types=1);

namespace App\Mapper\AI;

use App\Exception\ValidationException;

final readonly class UpdateProviderInputMapper
{
    /**
     * @return array{name:string, description:?string, secret:?string} secret = null keeps the current value
     */
    public function map(array $data): array
    {
        $name = $data['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            throw new ValidationException('Nazwa dostawcy jest wymagana.');
        }

        $description = $data['description'] ?? null;
        if ($description !== null && (!is_string($description) || trim($description) === '')) {
            $description = null;
        }

        $secret = $data['secret'] ?? null;
        if ($secret !== null && (!is_string($secret) || trim($secret) === '')) {
            throw new ValidationException('Klucz API nie może być pusty.');
        }

        return [
            'name' => trim($name),
            'description' => is_string($description) ? trim($description) : null,
            'secret' => is_string($secret) ? trim($secret) : null,
        ];
    }
}
