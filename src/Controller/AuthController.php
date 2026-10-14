<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Infrastructure\Http\JsonResponder;
use App\Mapper\Auth\LoginInputMapper;
use App\Mapper\Auth\RegisterInputMapper;
use App\Repository\UserRepository;
use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private readonly RegisterInputMapper $registerInputMapper,
        private readonly LoginInputMapper $loginInputMapper,
        private readonly AuthService $authService,
        private readonly UserRepository $userRepository
    ) {
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = (array)$request->getParsedBody();
        $input = $this->registerInputMapper->map($data);

        $confirmationSent = $this->authService->register($input);
        return JsonResponder::respond($response, [
            'message' => 'Konto zostało utworzone.',
            'confirmation_sent' => $confirmationSent,
        ], 201);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = (array)$request->getParsedBody();
        $input = $this->loginInputMapper->map($data);

        $result = $this->authService->login($input);

        return JsonResponder::respond($response, [
            'token' => $result->token,
        ])->withAddedHeader('Set-Cookie', $this->sessionCookie($request, $result->token, strtotime($result->expiresAt)));
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $token = $this->resolveToken($request);
        } catch (UnauthorizedException) {
            $token = '';
        }
        $cookieToken = $request->getCookieParams()['token'] ?? '';
        foreach (array_unique([$token, is_string($cookieToken) ? $cookieToken : '']) as $sessionToken) {
            if ($sessionToken !== '') {
                $this->authService->logout($sessionToken);
            }
        }
        return $response->withStatus(204)
            ->withHeader('Cache-Control', 'no-store')
            ->withAddedHeader('Set-Cookie', $this->sessionCookie($request, '', 0));
    }

    private function sessionCookie(ServerRequestInterface $request, string $token, int $expiresAt): string
    {
        $secure = $request->getUri()->getScheme() === 'https' || ($_ENV['APP_ENV'] ?? 'dev') === 'prod';
        return 'token='.rawurlencode($token)
            .'; Expires='.gmdate('D, d M Y H:i:s', $expiresAt).' GMT'
            .'; Path=/; HttpOnly; SameSite='.($secure ? 'Strict' : 'Lax')
            .($secure ? '; Secure' : '');
    }

    public function verifyEmail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $request->getQueryParams()['token'] ?? '';
        if ($token === '') {
            throw new ValidationException('Token is required');
        }

        $this->authService->verifyEmail($token);

        return JsonResponder::respond($response, ['message' => 'Email confirmed successfully']);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $this->resolveToken($request);
        $userId = $this->authService->validateToken($token);

        $user = $this->userRepository->findById($userId);

        return JsonResponder::respond($response, [
            'id' => $user['id'],
            'email' => $user['email'],
        ]);
    }

    private function resolveToken(ServerRequestInterface $request): string
    {
        $header = $request->getHeaderLine('Authorization');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        $cookies = $request->getCookieParams();
        if (!empty($cookies['token'])) {
            return $cookies['token'];
        }

        throw new UnauthorizedException('Missing or invalid Authorization header');
    }
}
