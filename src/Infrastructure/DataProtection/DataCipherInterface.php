<?php

declare(strict_types=1);

namespace App\Infrastructure\DataProtection;

interface DataCipherInterface
{
    public function encrypt(string $value, string $context): string;
    public function decrypt(string $value, string $context): string;
    public function emailIndex(string $email): string;
}
