<?php

declare(strict_types=1);

use App\Exception\NotFoundException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;

return function (App $app) {
    $logger = $app->getContainer()->get(LoggerInterface::class);

    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    // Custom Error Handling Middleware
    $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($logger): ResponseInterface {
        $routeContext = [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
        ];
        $userId = $request->getAttribute('user_id');
        if ($userId !== null) {
            $routeContext['user_id'] = (int)$userId;
        }

        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            $logger->info($e->getMessage(), [
                ...$routeContext,
                'origin' => basename($e->getFile()).':'.$e->getLine(),
            ]);

            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => $e->getMessage()], 422);
        } catch (\App\Exception\LoginRateLimitException $e) {
            $logger->warning('Login rate limit exceeded', [
                ...$routeContext,
                'retry_after' => $e->retryAfter,
                'origin' => basename($e->getFile()).':'.$e->getLine(),
            ]);
            $response = \App\Infrastructure\Http\JsonResponder::respond(
                new Response(),
                ['error' => $e->getMessage()],
                429
            );
            return $response->withHeader('Retry-After', (string)$e->retryAfter);
        } catch (\App\Exception\EmailConfirmationRequiredException $e) {
            $logger->info('Email confirmation required', [
                ...$routeContext,
                'origin' => basename($e->getFile()).':'.$e->getLine(),
            ]);

            return \App\Infrastructure\Http\JsonResponder::respond(
                new Response(),
                ['error' => $e->getMessage(), 'code' => 'email_confirmation_required'],
                403
            );
        } catch (UnauthorizedException $e) {
            $logger->warning($e->getMessage(), [
                ...$routeContext,
                'origin' => basename($e->getFile()).':'.$e->getLine(),
            ]);

            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => $e->getMessage()], 401);
        } catch (NotFoundException|HttpNotFoundException $e) {
            $logger->warning($e->getMessage(), [
                ...$routeContext,
                'origin' => basename($e->getFile()).':'.$e->getLine(),
            ]);

            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => $e->getMessage()], 404);
        } catch (\App\Exception\AiConnectionException $e) {
            $logger->error('AI provider unavailable', [
                'reason' => $e->reason,
                ...$routeContext,
                'origin' => basename($e->getFile()).':'.$e->getLine(),
            ]);
            return \App\Infrastructure\Http\JsonResponder::respond(
                new Response(),
                ['error' => 'Przepraszamy, połączenie z AI jest chwilowo niedostępne. Pracujemy nad jak najszybszym usunięciem problemu.'],
                503
            )->withHeader('X-AI-Error', $e->reason);
        } catch (\App\Exception\ServiceUnavailableException $e) {
            $logger->warning($e->getMessage(), [
                ...$routeContext,
                'origin' => basename($e->getFile()).':'.$e->getLine(),
            ]);
            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => $e->getMessage()], 503);
        } catch (\App\Exception\DataProtectionException $e) {
            $logger->error('Data protection unavailable', [
                'reason' => $e->reason,
                ...$routeContext,
                'origin' => basename($e->getFile()).':'.$e->getLine(),
            ]);
            $response = new Response();
            return \App\Infrastructure\Http\JsonResponder::respond(
                $response,
                ['error' => 'Przepraszamy, wystąpił problem techniczny po naszej stronie. Pracujemy nad jego jak najszybszym usunięciem.'],
                503
            );
        } catch (\Slim\Exception\HttpMethodNotAllowedException $e) {
            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => 'Metoda HTTP jest niedozwolona.'], 405)
                ->withHeader('Allow', implode(', ', $e->getAllowedMethods()));
        } catch (Throwable $e) {
            // Only closed diagnostic codes and the throw site; never messages, queries or bound values.
            $context = [
                'type' => get_class($e),
                'origin' => basename($e->getFile()).':'.$e->getLine(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                ...$routeContext,
            ];
            if ($e instanceof PDOException) {
                $context['sqlstate'] = (string)$e->getCode();
                $context['driver_code'] = $e->errorInfo[1] ?? null;
            }
            $logger->error('Application operation failed', $context);

            return \App\Infrastructure\Http\JsonResponder::respond(
                new Response(),
                ['error' => 'Przepraszamy, wystąpił nieoczekiwany problem. Pracujemy nad jego jak najszybszym usunięciem.'],
                500
            );
        }
    });

    $app->addErrorMiddleware(
        $app->getContainer()->get('settings')['displayErrorDetails'],
        true,
        true
    );
};
