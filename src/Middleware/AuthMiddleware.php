<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\SessionTokenResolver;
use App\Service\SessionAuthenticatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionAuthenticatorInterface $auth, private SessionTokenResolver $resolver)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $this->auth->validateToken($this->resolver->resolveToken($request));
        return $handler->handle($request->withAttribute('userId', $userId));
    }
}
