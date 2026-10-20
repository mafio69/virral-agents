<?php

declare(strict_types=1);

namespace App\Dto\Chat;

final readonly class ListModelsInput
{
    public function __construct(
        public ?string $connection = null,
    ) {
    }
}
