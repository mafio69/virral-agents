<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\ValidationException;
use App\Mapper\Auth\ForgotPasswordInputMapper;
use App\Mapper\Auth\ResetPasswordInputMapper;
use PHPUnit\Framework\TestCase;

final class AuthResetPasswordApiTest extends TestCase
{
    private ForgotPasswordInputMapper $forgotMapper;
    private ResetPasswordInputMapper $resetMapper;

    protected function setUp(): void
    {
        $this->forgotMapper = new ForgotPasswordInputMapper();
        $this->resetMapper = new ResetPasswordInputMapper();
    }

    public function testForgotMapperValidEmail(): void
    {
        $input = $this->forgotMapper->map(['email' => '  user@example.com  ']);
        $this->assertSame('user@example.com', $input->email);
    }

    public function testForgotMapperEmptyEmailThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->forgotMapper->map(['email' => '']);
    }

    public function testForgotMapperInvalidEmailThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->forgotMapper->map(['email' => 'not-an-email']);
    }

    public function testResetMapperValidData(): void
    {
        $token = str_repeat('b', 64);
        $input = $this->resetMapper->map([
            'token' => $token,
            'password' => 'newpassword123',
        ]);

        $this->assertSame($token, $input->token);
        $this->assertSame('newpassword123', $input->password);
    }

    public function testResetMapperInvalidTokenThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->resetMapper->map([
            'token' => 'short-token',
            'password' => 'newpassword123',
        ]);
    }

    public function testResetMapperShortPasswordThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->resetMapper->map([
            'token' => str_repeat('b', 64),
            'password' => 'short',
        ]);
    }
}
