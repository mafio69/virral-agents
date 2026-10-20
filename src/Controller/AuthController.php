<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Infrastructure\Http\JsonResponder;
use App\Mapper\Auth\LoginInputMapper;
use App\Mapper\Auth\RegisterInputMapper;
use App\Mapper\Auth\VerifyEmailInputMapper;
use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private readonly RegisterInputMapper $registerInputMapper,
        private readonly LoginInputMapper $loginInputMapper,
        private readonly AuthService $authService,
        private readonly \App\Http\SessionTokenResolver $sessionTokens,
        private readonly string $environment = 'prod',
        private readonly \App\Http\ClientIpResolver $clientIp = new \App\Http\ClientIpResolver(),
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

        $result = $this->authService->login($input, $this->clientIp->resolve($request));

        return JsonResponder::respond($response, [
            'token' => $result->token,
        ])->withAddedHeader('Set-Cookie', $this->sessionCookie($request, $result->token, strtotime($result->expiresAt)));
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $token = $this->sessionTokens->resolveToken($request);
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
        // Only an explicit dev environment may issue a cookie without Secure over plain HTTP.
        $secure = $request->getUri()->getScheme() === 'https' || $this->environment !== 'dev';
        return 'token='.rawurlencode($token)
            .'; Expires='.gmdate('D, d M Y H:i:s', $expiresAt).' GMT'
            .'; Path=/; HttpOnly; SameSite='.($secure ? 'Strict' : 'Lax')
            .($secure ? '; Secure' : '');
    }

    public function verifyEmail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = (new VerifyEmailInputMapper())->map($request->getQueryParams());
        $this->authService->verifyEmail($input);

        return JsonResponder::respond($response, ['message' => 'Email confirmed successfully']);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int)$request->getAttribute('userId');

        $user = $this->authService->currentUser($userId);

        return JsonResponder::respond($response, $user);
    }

}
