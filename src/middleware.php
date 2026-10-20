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
        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            $logger->info($e->getMessage());

            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => $e->getMessage()], 422);
        } catch (\App\Exception\LoginRateLimitException $e) {
            $response = \App\Infrastructure\Http\JsonResponder::respond(
                new Response(), ['error' => $e->getMessage()], 429
            );
            return $response->withHeader('Retry-After', (string)$e->retryAfter);
        } catch (\App\Exception\EmailConfirmationRequiredException $e) {
            return \App\Infrastructure\Http\JsonResponder::respond(
                new Response(), ['error' => $e->getMessage(), 'code' => 'email_confirmation_required'], 403
            );
        } catch (UnauthorizedException $e) {
            $logger->warning($e->getMessage());

            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => $e->getMessage()], 401);
        } catch (NotFoundException|HttpNotFoundException $e) {
            $logger->warning($e->getMessage());

            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => $e->getMessage()], 404);
        } catch (\App\Exception\AiConnectionException $e) {
            $logger->error('AI provider unavailable', ['reason' => $e->reason]);
            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => 'Połączenie z AI jest niedostępne.'], 503)
                ->withHeader('X-AI-Error', $e->reason);
        } catch (\App\Exception\ServiceUnavailableException $e) {
            $logger->warning($e->getMessage());
            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => $e->getMessage()], 503);
        } catch (\App\Exception\DataProtectionException) {
            $logger->error('Data protection unavailable');
            $response = new Response();
            return \App\Infrastructure\Http\JsonResponder::respond($response,
                ['error' => 'Ochrona danych jest niedostępna. Skontaktuj się z administratorem.'], 503);
        } catch (\Slim\Exception\HttpMethodNotAllowedException $e) {
            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => 'Metoda HTTP jest niedozwolona.'], 405)
                ->withHeader('Allow', implode(', ', $e->getAllowedMethods()));
        } catch (Throwable $e) {
            // Only closed diagnostic codes and the throw site; never messages, queries or bound values.
            $context = ['type' => get_class($e), 'origin' => basename($e->getFile()).':'.$e->getLine()];
            if ($e instanceof PDOException) {
                $context['sqlstate'] = (string)$e->getCode();
                $context['driver_code'] = $e->errorInfo[1] ?? null;
            }
            $logger->error('Application operation failed', $context);

            return \App\Infrastructure\Http\JsonResponder::respond(new Response(), ['error' => 'Internal Server Error'], 500);
        }
    });

    $app->addErrorMiddleware(
        $app->getContainer()->get('settings')['displayErrorDetails'],
        true,
        true
    );
};
