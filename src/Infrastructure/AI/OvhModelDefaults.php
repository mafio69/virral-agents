<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

final class OvhModelDefaults
{
    public const MODEL = 'Qwen3.6-27B';

    public static function resolve(?string $model, string $fallback = self::MODEL): string
    {
        $model = trim($model ?? '');
        return $model === '' || $model === 'Qwen3-Coder-30B-A3B-Instruct' ? $fallback : $model;
    }
}
