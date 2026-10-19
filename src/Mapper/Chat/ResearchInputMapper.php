<?php

declare(strict_types=1);

namespace App\Mapper\Chat;

use App\Exception\ValidationException;

final class ResearchInputMapper
{
    public function map(string $operation, array $query, array $data): array
    {
        $scope = $query['chat_id'] ?? '0';
        if (!(is_string($scope) || is_int($scope)) || !preg_match('/^\d{1,9}$/D', (string)$scope)) {
            throw new ValidationException('Nieprawidłowa rozmowa.');
        }
        $args = match ($operation) {
            'state', 'prepare' => [],
            'propose' => [is_array($data['request'] ?? null) ? $data['request'] : throw new ValidationException('Brak prośby WWW.')],
            'decide' => [$this->id($data['id'] ?? null), $this->boolean($data['allow'] ?? null)],
            'select' => [$this->id($data['id'] ?? null), $this->boolean($data['selected'] ?? null)],
            default => throw new ValidationException('Nieznana operacja.'),
        };
        return [(int)$scope, $args];
    }
    private function id(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{32,64}$/D', $value)) {
            throw new ValidationException('Nieprawidłowy identyfikator.');
        }
        return $value;
    }
    private function boolean(mixed $value): bool
    {
        return is_bool($value) ? $value : throw new ValidationException('Wymagana jawna decyzja.');
    }
}
