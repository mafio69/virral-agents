<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;

final class InlineImage
{
    /** @return array{mime_type: string, data: string} */
    public static function parse(string $url): array
    {
        if (!preg_match('#^data:(image/(?:png|jpeg|webp));base64,([A-Za-z0-9+/]+={0,2})$#D', $url, $matches)
            || base64_decode($matches[2], true) === false) {
            throw new AiConnectionException('invalid_response');
        }
        return ['mime_type' => $matches[1], 'data' => $matches[2]];
    }
}
