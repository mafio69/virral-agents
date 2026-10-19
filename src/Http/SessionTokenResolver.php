<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\UnauthorizedException;
use Psr\Http\Message\ServerRequestInterface;

final class SessionTokenResolver
{
    public function resolveToken(ServerRequestInterface $request): string
    {
        $header = $request->getHeaderLine('Authorization');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        $token = $request->getCookieParams()['token'] ?? null;
        if (is_string($token) && $token !== '') {
            return $token;
        }
        throw new UnauthorizedException('Missing or invalid Authorization header');
    }
}
