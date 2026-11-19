<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Guards admin-only endpoints. A non-admin gets a 404 so the endpoints stay invisible.
 * Runs after AuthMiddleware, which sets the `role` request attribute.
 */
final readonly class AdminOnlyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute('role') !== 'admin') {
            return JsonResponder::respond(new Response(), ['error' => 'Not found'], 404);
        }

        return $handler->handle($request);
    }
}
