<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    public static function respond(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'no-store');
    }
}
