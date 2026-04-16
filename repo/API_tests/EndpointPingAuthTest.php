<?php

declare(strict_types=1);

namespace tests\api;

use app\model\User;
use app\model\Session;

/**
 * HTTP endpoint tests for:
 *   GET  /api/v1/ping
 *   POST /api/v1/auth/login
 *   POST /api/v1/auth/logout
 *   POST /api/v1/auth/unlock
 */
class EndpointPingAuthTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->token = '';
        $this->cleanupUsersLike('http-ping-%');
        $this->cleanupUsersLike('http-test-admin%');
    }

    protected function tearDown(): void
    {
        $this->cleanupUsersLike('http-ping-%');
        $this->cleanupUsersLike('http-test-admin%');
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // GET /api/v1/ping
    // ------------------------------------------------------------------

    public function testPingReturns200WithoutAuth(): void
    {
        $res = $this->get('/api/v1/ping');

        $this->assertStatus(200, $res);
    }

    public function testPingResponseContainsSuccessFlag(): void
    {
        $res = $this->get('/api/v1/ping');

        $this->assertTrue($res['body']['success'] ?? false);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/auth/login
    // ------------------------------------------------------------------

    public function testLoginReturns200WithValidCredentials(): void
    {
        $this->ensureUser('http-ping-admin', 'administrator');

        $res = $this->post('/api/v1/auth/login', [
            'username' => 'http-ping-admin',
            'password' => 'HttpTest1!Pass',
        ]);

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
        $this->assertNotEmpty($res['body']['data']['access_token'] ?? '');
    }

    public function testLoginResponseContainsUserData(): void
    {
        $this->ensureUser('http-ping-admin', 'administrator');

        $res = $this->post('/api/v1/auth/login', [
            'username' => 'http-ping-admin',
            'password' => 'HttpTest1!Pass',
        ]);

        $data = $res['body']['data'] ?? [];
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('expires_at', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('http-ping-admin', $data['user']['username'] ?? '');
    }

    public function testLoginReturns401ForWrongPassword(): void
    {
        $this->ensureUser('http-ping-admin', 'administrator');

        $res = $this->post('/api/v1/auth/login', [
            'username' => 'http-ping-admin',
            'password' => 'wrong-password',
        ]);

        $this->assertStatus(401, $res);
        $this->assertFalse($res['body']['success'] ?? true);
    }

    public function testLoginReturns400WhenCredentialsMissing(): void
    {
        $res = $this->post('/api/v1/auth/login', []);

        $this->assertStatus(400, $res);
    }

    public function testLoginReturns401ForUnknownUser(): void
    {
        $res = $this->post('/api/v1/auth/login', [
            'username' => 'http-no-such-user-' . uniqid(),
            'password' => 'AnyPass1!',
        ]);

        $this->assertStatus(401, $res);
    }

    public function testLoginReturns403ForDisabledAccount(): void
    {
        $user = $this->ensureUser('http-ping-disabled', 'regular_user');
        $user->status = 'disabled';
        $user->save();

        $res = $this->post('/api/v1/auth/login', [
            'username' => 'http-ping-disabled',
            'password' => 'HttpTest1!Pass',
        ]);

        $this->assertStatus(403, $res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/auth/logout
    // ------------------------------------------------------------------

    public function testLogoutReturns200WhenAuthenticated(): void
    {
        $this->loginAsAdmin('http-test-admin');

        $res = $this->post('/api/v1/auth/logout');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testLogoutReturns401WhenUnauthenticated(): void
    {
        // No token set
        $res = $this->post('/api/v1/auth/logout');

        $this->assertUnauthorized($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/auth/unlock (requires users.password permission)
    // ------------------------------------------------------------------

    public function testUnlockReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/auth/unlock', ['user_id' => 1]);

        $this->assertUnauthorized($res);
    }

    public function testUnlockReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-ping-regular');
        $target = $this->ensureUser('http-ping-target', 'regular_user');

        $res = $this->post('/api/v1/auth/unlock', ['user_id' => $target->id]);

        $this->assertForbidden($res);
    }

    public function testUnlockReturns200ForAdmin(): void
    {
        $this->loginAsAdmin('http-test-admin');
        $target = $this->ensureUser('http-ping-target', 'regular_user');
        $target->locked_until = date('Y-m-d H:i:s', time() + 3600);
        $target->save();

        $res = $this->post('/api/v1/auth/unlock', ['user_id' => $target->id]);

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }
}
