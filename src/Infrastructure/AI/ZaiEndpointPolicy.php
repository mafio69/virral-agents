<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;

final class ZaiEndpointPolicy
{
    public const BASE_URI = 'https://api.z.ai/api/paas/v4/';
    public const CODING_BASE_URI = 'https://api.z.ai/api/coding/paas/v4/';
    public const ALLOWED_BASE_URIS = [
        self::BASE_URI,
        self::CODING_BASE_URI,
    ];

    public static function normalize(string $url): string
    {
        $normalized = rtrim(trim($url), '/');
        if (str_ends_with($normalized, '/chat/completions')) {
            $normalized = substr($normalized, 0, -strlen('/chat/completions'));
        }
        return $normalized === '' ? '' : $normalized.'/';
    }

    public static function isAllowed(string $url): bool
    {
        return in_array(self::normalize($url), self::ALLOWED_BASE_URIS, true);
    }

    public static function resolveConfiguredBaseUri(string $configured): ?string
    {
        if (trim($configured) === '') {
            return self::BASE_URI;
        }
        $normalized = self::normalize($configured);
        return in_array($normalized, self::ALLOWED_BASE_URIS, true) ? $normalized : null;
    }

    public static function assertAllowed(string $url): void
    {
        if (!self::isAllowed($url)) {
            throw new AiConnectionException('model_unavailable');
        }
    }

    public static function supportsImages(string $model): bool
    {
        return in_array($model, ['glm-5.3-flash', 'glm-5.3-flashx'], true);
    }
}
