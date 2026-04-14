<?php

declare(strict_types=1);

namespace tests\api;

use app\model\User;
use app\model\Role;
use app\service\AuthService;
use PHPUnit\Framework\TestCase;

/**
 * API tests verifying that RBAC middleware denies access correctly.
 * These tests exercise the permission model at the service layer since
 * we do not spin up an HTTP server in the test suite.
 */
class RbacApiTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthService();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Permission checks via hasPermission() — mirrors RBAC middleware
    // ------------------------------------------------------------------

    public function testRegularUserDoesNotHaveUsersReadPermission(): void
    {
        $user = $this->ensureUser('rbac-test-regular', 'regular_user');
        $this->assertFalse(
            $user->hasPermission('users.read'),
            'regular_user must not have users.read'
        );
    }

    public function testAdministratorHasUsersReadPermission(): void
    {
        $user = $this->ensureUser('rbac-test-admin', 'administrator');
        $this->assertTrue(
            $user->hasPermission('users.read'),
            'administrator must have users.read'
        );
    }

    public function testRegularUserDoesNotHaveViolationRulesPermission(): void
    {
        $user = $this->ensureUser('rbac-test-regular', 'regular_user');
        $this->assertFalse(
            $user->hasPermission('violations.rules'),
            'regular_user must not have violations.rules'
        );
    }

    public function testUnauthenticatedTokenValidationReturnsNull(): void
    {
        // No-token scenario: validateToken returns null for an invalid token,
        // which is what AuthMiddleware uses to return 401.
        $result = $this->authService->validateToken('not-a-real-token-' . uniqid());
        $this->assertNull($result, 'An invalid token must not authenticate');
    }

    public function testEmptyTokenValidationReturnsNull(): void
    {
        $result = $this->authService->validateToken('');
        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function ensureUser(string $username, string $role): User
    {
        $user = User::where('username', $username)->find();
        if (!$user) {
            $user = new User();
            $user->username = $username;
            $user->role     = $role;
            $user->status   = 'active';
            $user->setPassword('RbacTest1234');
            $user->save();
        }
        return $user;
    }

    private function cleanUp(): void
    {
        User::where('username', 'rbac-test-regular')->delete();
        User::where('username', 'rbac-test-admin')->delete();
    }
}
