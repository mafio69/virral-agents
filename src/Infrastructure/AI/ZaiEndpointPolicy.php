<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;

final class ZaiEndpointPolicy
{
    public const BASE_URI = 'https://api.z.ai/api/paas/v4/';

    public static function assertAllowed(string $url): void
    {
        if (rtrim($url, '/') !== rtrim(self::BASE_URI, '/')) {
            throw new AiConnectionException('model_unavailable');
        }
    }

    public static function supportsImages(string $model): bool
    {
        return in_array($model, ['glm-5.3-flash', 'glm-5.3-flashx'], true);
    }
}
