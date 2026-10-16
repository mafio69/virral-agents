<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\ChatController;
use App\Controller\TaskController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->get('/', function ($request, $response) {
        $body = $response->getBody();
        $body->write(file_get_contents(__DIR__.'/../public/ui/landing.html'));

        return $response->withHeader('Content-Type', 'text/html');
    });

    $app->get('/chat', function ($request, $response) {
        $response->getBody()->write(file_get_contents(__DIR__.'/../public/ui/chat.html'));

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    });

    $app->get('/test-api', function ($request, $response) {
        $body = $response->getBody();
        $body->write(file_get_contents(__DIR__.'/../public/ui/index.html'));

        return $response->withHeader('Content-Type', 'text/html');
    });

    $app->group('/api', function (RouteCollectorProxy $group) {
        // Auth
        $group->post('/auth/register', [AuthController::class, 'register']);
        $group->post('/auth/login', [AuthController::class, 'login']);
        $group->post('/auth/logout', [AuthController::class, 'logout']);
        $group->get('/auth/me', [AuthController::class, 'me']);
        $group->get('/auth/verify-email', [AuthController::class, 'verifyEmail']);

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
        
        // AI Models
        $group->get('/ai-connections', [ChatController::class, 'listConnections']);
        $group->get('/search/status', [ChatController::class, 'searchStatus']);
        $group->get('/ai-models', [ChatController::class, 'listModels']);
    });
};
