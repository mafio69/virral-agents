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

            $response = new Response();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));

            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        } catch (UnauthorizedException $e) {
            $logger->warning($e->getMessage());

            $response = new Response();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));

            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        } catch (NotFoundException|HttpNotFoundException $e) {
            $logger->warning($e->getMessage());

            $response = new Response();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));

            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        } catch (\App\Exception\AiConnectionException $e) {
            $logger->error('AI provider unavailable', ['reason' => $e->reason]);
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Połączenie z AI jest niedostępne.']));
            return $response->withStatus(503)->withHeader('Content-Type', 'application/json')
                ->withHeader('X-AI-Error', $e->reason);
        } catch (\App\Exception\DataProtectionException) {
            $logger->error('Data protection unavailable');
            $response = new Response();
            return \App\Infrastructure\Http\JsonResponder::respond($response,
                ['error' => 'Ochrona danych jest niedostępna. Skontaktuj się z administratorem.'], 503);
        } catch (Throwable $e) {
            $logger->error('Application operation failed', ['type' => get_class($e)]);

            $response = new Response();
            $error = ['error' => 'Internal Server Error'];
            $response->getBody()->write(json_encode($error));

            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    $app->addErrorMiddleware(
        $app->getContainer()->get('settings')['displayErrorDetails'],
        true,
        true
    );
};
