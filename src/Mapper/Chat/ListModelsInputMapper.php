<?php

declare(strict_types=1);

namespace App\Mapper\Chat;

use App\Dto\Chat\ListModelsInput;
use App\Exception\ValidationException;

final readonly class ListModelsInputMapper
{
    public function map(array $query): ListModelsInput
    {
        $connection = $query['connection'] ?? null;
        if ($connection !== null && !is_string($connection)) {
            throw new ValidationException('Nieznane połączenie AI.');
        }

        return new ListModelsInput($connection);
    }
}
