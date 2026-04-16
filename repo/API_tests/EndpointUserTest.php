<?php

declare(strict_types=1);

namespace tests\api;

use app\model\User;

/**
 * HTTP endpoint tests for:
 *   GET    /api/v1/users
 *   GET    /api/v1/users/:id
 *   POST   /api/v1/users
 *   PUT    /api/v1/users/:id
 *   DELETE /api/v1/users/:id
 *   PUT    /api/v1/users/:id/role
 *   PUT    /api/v1/users/:id/password
 */
class EndpointUserTest extends HttpTestCase
{
    private User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupUsersLike('http-user-%');
        $this->cleanupUsersLike('http-test-admin%');
        $this->targetUser = $this->ensureUser('http-user-target', 'regular_user');
    }

    protected function tearDown(): void
    {
        $this->cleanupUsersLike('http-user-%');
        $this->cleanupUsersLike('http-test-admin%');
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // GET /api/v1/users — requires users.read (administrator only)
    // ------------------------------------------------------------------

    public function testListUsersReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/users');
        $this->assertUnauthorized($res);
    }

    public function testListUsersReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-user-regular');
        $res = $this->get('/api/v1/users');
        $this->assertForbidden($res);
    }

    public function testListUsersReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/users');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
        $this->assertArrayHasKey('list', $res['body']['data'] ?? []);
    }

    public function testListUsersResponseContainsPaginationFields(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/users');

        $data = $res['body']['data'] ?? [];
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertArrayHasKey('list', $data);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/users/:id
    // ------------------------------------------------------------------

    public function testGetUserReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/users/' . $this->targetUser->id);
        $this->assertUnauthorized($res);
    }

    public function testGetUserReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/users/' . $this->targetUser->id);

        $this->assertStatus(200, $res);
        $data = $res['body']['data'] ?? [];
        $this->assertEquals($this->targetUser->id, $data['id'] ?? null);
    }

    public function testGetUserReturns404ForNonExistentId(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/users/999999');

        $this->assertNotFound($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/users — requires users.create
    // ------------------------------------------------------------------

    public function testCreateUserReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/users', [
            'username' => 'http-user-new',
            'password' => 'HttpTest1!Pass',
        ]);
        $this->assertUnauthorized($res);
    }

    public function testCreateUserReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-user-regular');
        $res = $this->post('/api/v1/users', [
            'username' => 'http-user-new',
            'password' => 'HttpTest1!Pass',
        ]);
        $this->assertForbidden($res);
    }

    public function testCreateUserReturns201ForAdmin(): void
    {
        $this->loginAsAdmin();
        $username = 'http-user-created-' . uniqid();
        $res = $this->post('/api/v1/users', [
            'username' => $username,
            'password' => 'HttpTest1!Pass',
            'role'     => 'regular_user',
        ]);

        $this->assertStatus(201, $res);
        $this->assertSuccess($res);
        $this->assertEquals($username, $res['body']['data']['username'] ?? '');
    }

    public function testCreateUserReturns400ForDuplicateUsername(): void
    {
        $this->loginAsAdmin();
        // targetUser already exists
        $res = $this->post('/api/v1/users', [
            'username' => 'http-user-target',
            'password' => 'HttpTest1!Pass',
        ]);

        $this->assertStatus(400, $res);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/users/:id — requires users.update
    // ------------------------------------------------------------------

    public function testUpdateUserReturns401WhenUnauthenticated(): void
    {
        $res = $this->put('/api/v1/users/' . $this->targetUser->id, ['status' => 'disabled']);
        $this->assertUnauthorized($res);
    }

    public function testUpdateUserReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->put('/api/v1/users/' . $this->targetUser->id, ['status' => 'disabled']);

        $this->assertStatus(200, $res);
        $this->assertEquals('disabled', $res['body']['data']['status'] ?? '');
    }

    // ------------------------------------------------------------------
    // DELETE /api/v1/users/:id — requires users.delete
    // ------------------------------------------------------------------

    public function testDeleteUserReturns401WhenUnauthenticated(): void
    {
        $res = $this->delete('/api/v1/users/' . $this->targetUser->id);
        $this->assertUnauthorized($res);
    }

    public function testDeleteUserReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $victim = $this->ensureUser('http-user-to-delete', 'regular_user');

        $res = $this->delete('/api/v1/users/' . $victim->id);

        $this->assertStatus(200, $res);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/users/:id/role — requires users.update
    // ------------------------------------------------------------------

    public function testChangeRoleReturns401WhenUnauthenticated(): void
    {
        $res = $this->put('/api/v1/users/' . $this->targetUser->id . '/role', ['role' => 'reviewer']);
        $this->assertUnauthorized($res);
    }

    public function testChangeRoleReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->put('/api/v1/users/' . $this->targetUser->id . '/role', ['role' => 'reviewer']);

        $this->assertStatus(200, $res);
        $this->assertEquals('reviewer', $res['body']['data']['role'] ?? '');
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/users/:id/password — requires users.password
    // ------------------------------------------------------------------

    public function testResetPasswordReturns401WhenUnauthenticated(): void
    {
        $res = $this->put('/api/v1/users/' . $this->targetUser->id . '/password', []);
        $this->assertUnauthorized($res);
    }

    public function testResetPasswordReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->put('/api/v1/users/' . $this->targetUser->id . '/password', []);

        $this->assertStatus(200, $res);
        $this->assertNotEmpty($res['body']['data']['temp_password'] ?? '');
    }
}
