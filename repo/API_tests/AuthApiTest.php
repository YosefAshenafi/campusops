<?php

declare(strict_types=1);

namespace tests\api;

use app\model\User;
use app\model\Session;
use app\service\AuthService;
use PHPUnit\Framework\TestCase;

/**
 * API contract tests for AuthService - tests service behavior with mock repositories.
 * These validate expected request/response contracts without network calls.
 */
class AuthApiTest extends TestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService();
    }

    public function testLoginReturnsInvalidCredentialsForUnknownUser(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(401);

        $this->service->login('nonexistent-user', 'bad-password');
    }

    public function testLoginThrowsForDisabledAccount(): void
    {
        $user = $this->createMockUser('testuser', 'password123', 'disabled');
        
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $this->service->login('testuser', 'password123');
    }

    public function testLoginThrowsForLockedAccount(): void
    {
        $user = $this->createMockUser('testuser', 'password123', 'active');
        $user->locked_until = date('Y-m-d H:i:s', time() + 3600);
        $user->save();

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(429);

        $this->service->login('testuser', 'password123');
    }

    public function testLoginReturnsUserAndTokenOnSuccess(): void
    {
        $user = $this->createMockUser('testuser', 'password123', 'active');
        
        $result = $this->service->login('testuser', 'password123');

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertIsString($result['token']);
        $this->assertEquals(64, strlen($result['token']));
    }

    public function testLoginIncrementsFailedAttemptsOnWrongPassword(): void
    {
        $user = $this->createMockUser('testuser', 'password123', 'active');

        try {
            $this->service->login('testuser', 'wrong-password');
        } catch (\Exception $e) {
            // Expected
        }

        $user = User::find($user->id);
        $this->assertEquals(1, $user->failed_attempts);
    }

    public function testLoginLocksAfterFiveFailedAttempts(): void
    {
        $user = $this->createMockUser('testuser', 'password123', 'active');
        $user->failed_attempts = 4;
        $user->save();

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(429);

        $this->service->login('testuser', 'wrong-password');
    }

    public function testLogoutInvalidatesSession(): void
    {
        $user = $this->createMockUser('testuser', 'password123', 'active');
        $session = Session::createForUser($user->id);
        $token = $session->token;

        $this->service->logout($token);

        $this->assertNull(Session::where('token', $token)->find());
    }

    public function testValidateTokenReturnsUserForValidToken(): void
    {
        $user = $this->createMockUser('testuser', 'password123', 'active');
        $session = Session::createForUser($user->id);

        $result = $this->service->validateToken($session->token);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }

    public function testValidateTokenReturnsNullForInvalidToken(): void
    {
        $result = $this->service->validateToken('invalid-token-123');

        $this->assertNull($result);
    }

    public function testUnlockAccountResetsFailedAttempts(): void
    {
        $user = $this->createMockUser('testuser', 'password123', 'active');
        $user->failed_attempts = 5;
        $user->locked_until = date('Y-m-d H:i:s', time() + 3600);
        $user->save();

        $this->service->unlockAccount($user->id);

        $user->refresh();
        $this->assertEquals(0, $user->failed_attempts);
        $this->assertNull($user->locked_until);
    }

    private function createMockUser(string $username, string $password, string $status): User
    {
        $user = User::where('username', $username)->find();
        if (!$user) {
            $user = new User();
            $user->username = $username;
        }
        $user->status = $status;
        $user->failed_attempts = 0;
        $user->locked_until = null;
        $user->setPassword($password);
        $user->save();
        return $user;
    }
}