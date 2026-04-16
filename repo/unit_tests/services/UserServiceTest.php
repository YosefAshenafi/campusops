<?php

declare(strict_types=1);

namespace tests\services;

use app\model\User;
use app\service\UserService;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $service;
    private static int $userCounter = 1000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserService();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // listUsers
    // ------------------------------------------------------------------

    public function testListUsersReturnsPaginatedResults(): void
    {
        $this->createTestUser('unit-test-list-user-a', 'regular_user');
        $this->createTestUser('unit-test-list-user-b', 'regular_user');

        $result = $this->service->listUsers();

        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertGreaterThanOrEqual(2, $result['total']);
    }

    public function testListUsersFiltersByRole(): void
    {
        $this->createTestUser('unit-test-role-admin', 'administrator');
        $this->createTestUser('unit-test-role-user', 'regular_user');

        $result = $this->service->listUsers(1, 20, 'administrator');

        $usernames = array_column($result['list'], 'username');
        $this->assertContains('unit-test-role-admin', $usernames);
        $this->assertNotContains('unit-test-role-user', $usernames);
    }

    public function testListUsersFiltersByKeyword(): void
    {
        $this->createTestUser('unit-test-search-xyz', 'regular_user');
        $this->createTestUser('unit-test-other-abc', 'regular_user');

        $result = $this->service->listUsers(1, 20, '', '', 'search-xyz');

        $usernames = array_column($result['list'], 'username');
        $this->assertContains('unit-test-search-xyz', $usernames);
        $this->assertNotContains('unit-test-other-abc', $usernames);
    }

    // ------------------------------------------------------------------
    // getUser
    // ------------------------------------------------------------------

    public function testGetUserReturnsCorrectData(): void
    {
        $user = $this->createTestUser('unit-test-get-user', 'regular_user');

        $result = $this->service->getUser($user->id);

        $this->assertEquals('unit-test-get-user', $result['username']);
        $this->assertEquals('regular_user', $result['role']);
        $this->assertEquals('active', $result['status']);
    }

    public function testGetUserThrows404WhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->getUser(999999);
    }

    // ------------------------------------------------------------------
    // createUser
    // ------------------------------------------------------------------

    public function testCreateUserSucceeds(): void
    {
        $actor = $this->mockUser();
        $result = $this->service->createUser([
            'username' => 'unit-test-create-ok',
            'password' => 'SecurePass123',
            'role'     => 'regular_user',
        ], $actor);

        $this->assertEquals('unit-test-create-ok', $result['username']);
        $this->assertEquals('regular_user', $result['role']);
        $this->assertEquals('active', $result['status']);
    }

    public function testCreateUserThrowsOnDuplicateUsername(): void
    {
        $actor = $this->mockUser();
        $this->createTestUser('unit-test-dup-user', 'regular_user');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->createUser([
            'username' => 'unit-test-dup-user',
            'password' => 'SecurePass123',
        ], $actor);
    }

    public function testCreateUserThrowsWhenUsernameTooShort(): void
    {
        $actor = $this->mockUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->createUser([
            'username' => 'ab',
            'password' => 'SecurePass123',
        ], $actor);
    }

    public function testCreateUserThrowsWhenPasswordTooShort(): void
    {
        $actor = $this->mockUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->createUser([
            'username' => 'unit-test-shortpw',
            'password' => 'short',
        ], $actor);
    }

    // ------------------------------------------------------------------
    // updateUser
    // ------------------------------------------------------------------

    public function testUpdateUserChangesUsername(): void
    {
        $actor = $this->mockUser();
        $user = $this->createTestUser('unit-test-update-before', 'regular_user');

        $result = $this->service->updateUser($user->id, ['username' => 'unit-test-update-after'], $actor);

        $this->assertEquals('unit-test-update-after', $result['username']);
    }

    public function testUpdateUserChangesStatus(): void
    {
        $actor = $this->mockUser();
        $user = $this->createTestUser('unit-test-update-status', 'regular_user');

        $result = $this->service->updateUser($user->id, ['status' => 'disabled'], $actor);

        $this->assertEquals('disabled', $result['status']);
    }

    public function testUpdateUserThrows404WhenNotFound(): void
    {
        $actor = $this->mockUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->updateUser(999999, ['username' => 'unit-test-ghost'], $actor);
    }

    public function testUpdateUserThrowsOnDuplicateUsername(): void
    {
        $actor = $this->mockUser();
        $this->createTestUser('unit-test-taken-name', 'regular_user');
        $user = $this->createTestUser('unit-test-rename-me', 'regular_user');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->updateUser($user->id, ['username' => 'unit-test-taken-name'], $actor);
    }

    // ------------------------------------------------------------------
    // deleteUser
    // ------------------------------------------------------------------

    public function testDeleteUserSoftDeletesBySettingStatusDisabled(): void
    {
        $user = $this->createTestUser('unit-test-delete-target', 'regular_user');
        $actor = $this->mockUser(999);

        $this->service->deleteUser($user->id, $actor);

        $fresh = User::find($user->id);
        $this->assertEquals('disabled', $fresh->status);
    }

    public function testDeleteUserThrowsWhenDeletingOwnAccount(): void
    {
        $user = $this->createTestUser('unit-test-self-delete', 'regular_user');
        $actor = $this->mockUser($user->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->deleteUser($user->id, $actor);
    }

    public function testDeleteUserThrows404WhenNotFound(): void
    {
        $actor = $this->mockUser(999);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->deleteUser(999999, $actor);
    }

    // ------------------------------------------------------------------
    // changeRole
    // ------------------------------------------------------------------

    public function testChangeRoleUpdatesRole(): void
    {
        $actor = $this->mockUser();
        $user = $this->createTestUser('unit-test-change-role', 'regular_user');

        $result = $this->service->changeRole($user->id, 'administrator', $actor);

        $this->assertEquals('administrator', $result['role']);
    }

    public function testChangeRoleThrowsOnInvalidRole(): void
    {
        $actor = $this->mockUser();
        $user = $this->createTestUser('unit-test-invalid-role', 'regular_user');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->changeRole($user->id, 'nonexistent_role', $actor);
    }

    public function testChangeRoleThrows404WhenUserNotFound(): void
    {
        $actor = $this->mockUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->changeRole(999999, 'administrator', $actor);
    }

    // ------------------------------------------------------------------
    // resetPassword
    // ------------------------------------------------------------------

    public function testResetPasswordReturnsTempPassword(): void
    {
        $actor = $this->mockUser();
        $user = $this->createTestUser('unit-test-reset-pw', 'regular_user');

        $result = $this->service->resetPassword($user->id, $actor);

        $this->assertEquals($user->id, $result['user_id']);
        $this->assertNotEmpty($result['temp_password']);
        $this->assertIsString($result['temp_password']);
    }

    public function testResetPasswordThrows404WhenNotFound(): void
    {
        $actor = $this->mockUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->resetPassword(999999, $actor);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function mockUser(int $id = 1): object
    {
        return new class($id) {
            public int $id;
            public string $role = 'administrator';
            public function __construct(int $id) { $this->id = $id; }
        };
    }

    private function createTestUser(string $username, string $role): User
    {
        $user = new User();
        $user->username = $username;
        $user->role = $role;
        $user->status = 'active';
        $user->setPassword('TestPassword123');
        $user->save();
        return $user;
    }

    private function cleanUp(): void
    {
        $prefixes = [
            'unit-test-list-user-a', 'unit-test-list-user-b',
            'unit-test-role-admin', 'unit-test-role-user',
            'unit-test-search-xyz', 'unit-test-other-abc',
            'unit-test-get-user',
            'unit-test-create-ok',
            'unit-test-dup-user',
            'unit-test-shortpw',
            'unit-test-update-before', 'unit-test-update-after',
            'unit-test-update-status',
            'unit-test-taken-name', 'unit-test-rename-me',
            'unit-test-delete-target',
            'unit-test-self-delete',
            'unit-test-change-role',
            'unit-test-invalid-role',
            'unit-test-reset-pw',
        ];

        User::whereIn('username', $prefixes)->delete();
    }
}
