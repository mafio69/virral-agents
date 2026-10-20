<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\ChatController;
use App\Controller\TaskController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $settings = $app->getContainer()->get('settings');
    $development = ($settings['environment'] ?? 'prod') === 'dev';

    $app->get('/', function ($request, $response) use ($development) {
        $html = file_get_contents(__DIR__.'/../public/ui/landing.html');
        if (!$development) {
            // The /test-api panel exists only in dev; do not show links that would return 404.
            $html = preg_replace('~<!-- dev-only -->.*?<!-- /dev-only -->~s', '', $html);
        }
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html');
    });

    $app->get('/chat', function ($request, $response) {
        $response->getBody()->write(file_get_contents(__DIR__.'/../public/ui/chat.html'));

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    });

    if ($development) {
        $app->get('/test-api', function ($request, $response) {
            $response->getBody()->write(file_get_contents(__DIR__.'/../resources/views/test-api.html'));

            return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')
                ->withHeader('Cache-Control', 'no-store');
        });
    }

    $app->group('/api', function (RouteCollectorProxy $group) {
        // Auth
        $group->post('/auth/register', [AuthController::class, 'register']);
        $group->post('/auth/login', [AuthController::class, 'login']);
        $group->post('/auth/logout', [AuthController::class, 'logout']);
        $group->get('/auth/verify-email', [AuthController::class, 'verifyEmail']);

        $group->group('', function (RouteCollectorProxy $group) {
            $group->get('/auth/me', [AuthController::class, 'me']);
            // Tasks
            $group->get('/tasks', [TaskController::class, 'list']);
            $group->post('/tasks', [TaskController::class, 'create']);
            $group->put('/tasks/{id}', [TaskController::class, 'update']);
            $group->delete('/tasks/{id}', [TaskController::class, 'delete']);

            // Chats
            $group->get('/chats', [ChatController::class, 'list']);
            $group->post('/chats', [ChatController::class, 'create']);
            $group->get('/chats/{id}', [ChatController::class, 'get']);
            $group->post('/chats/{id}/messages', [ChatController::class, 'addMessage']);
            $group->post('/chats/{id}/messages/{messageId}/retry', [ChatController::class, 'retryMessage']);

            // Research and diagnostics
            $group->get('/search/status', [ChatController::class, 'searchStatus']);
            $group->get('/research', [ChatController::class, 'researchState']);
            $group->post('/research/actions', [ChatController::class, 'researchPropose']);
            $group->post('/research/decisions', [ChatController::class, 'researchDecide']);
            $group->put('/research/sources', [ChatController::class, 'researchSelect']);
            $group->post('/research/prepare', [ChatController::class, 'researchPrepare']);
        })->add(\App\Middleware\AuthMiddleware::class);

        $group->get('/ai-connections', [ChatController::class, 'listConnections']);
        $group->get('/ai-models', [ChatController::class, 'listModels']);
    });
};
