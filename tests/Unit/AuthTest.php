<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jef\Auth;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testGenerateCsrfTokenReturnsHexString(): void
    {
        $token = Auth::generateCsrfToken();

        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateCsrfTokenReturnsSameTokenPerSession(): void
    {
        $token1 = Auth::generateCsrfToken();
        $token2 = Auth::generateCsrfToken();

        $this->assertSame($token1, $token2);
    }

    public function testValidateCsrfTokenWithCorrectToken(): void
    {
        $token = Auth::generateCsrfToken();

        $this->assertTrue(Auth::validateCsrfToken($token));
    }

    public function testValidateCsrfTokenRejectsWrongToken(): void
    {
        Auth::generateCsrfToken();

        $this->assertFalse(Auth::validateCsrfToken('wrong-token'));
    }

    public function testValidateCsrfTokenRejectsEmptyToken(): void
    {
        Auth::generateCsrfToken();

        $this->assertFalse(Auth::validateCsrfToken(''));
    }

    public function testValidateCsrfTokenRejectsWhenNoTokenGenerated(): void
    {
        $this->assertFalse(Auth::validateCsrfToken('any-token'));
    }
}
