<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    public static function respond(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        // Invalid UTF-8 (e.g. from an AI provider) is replaced instead of turning the response into a 500.
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'no-store');
    }
}
