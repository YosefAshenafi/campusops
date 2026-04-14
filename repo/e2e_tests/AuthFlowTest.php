<?php

declare(strict_types=1);

namespace tests\e2e;

use app\model\User;
use app\model\Session;
use app\service\AuthService;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests for authentication flow.
 * Tests the complete user journey from registration to login to logout.
 */
class AuthFlowTest extends TestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService();
        
        User::where('username', 'e2e-test-user')->delete();
        User::where('username', 'e2e-test-user-2')->delete();
    }

    protected function tearDown(): void
    {
        User::where('username', 'e2e-test-user')->delete();
        User::where('username', 'e2e-test-user-2')->delete();
        
        parent::tearDown();
    }

    public function testFullAuthLifecycle(): void
    {
        $username = 'e2e-test-user';
        $password = 'securepassword123';
        
        $user = new User();
        $user->username = $username;
        $user->status = 'active';
        $user->setPassword($password);
        $user->save();
        
        $session = $this->service->login($username, $password);
        
        $this->assertArrayHasKey('user', $session);
        $this->assertArrayHasKey('token', $session);
        $this->assertEquals($username, $session['user']->username);
        
        $validatedUser = $this->service->validateToken($session['token']);
        $this->assertNotNull($validatedUser);
        
        $this->service->logout($session['token']);
        
        $stillValid = $this->service->validateToken($session['token']);
        $this->assertNull($stillValid);
    }

    public function testMultipleUserSessions(): void
    {
        $password = 'sharedpassword';
        
        $user1 = new User();
        $user1->username = 'e2e-test-user';
        $user1->status = 'active';
        $user1->setPassword($password);
        $user1->save();
        
        $user2 = new User();
        $user2->username = 'e2e-test-user-2';
        $user2->status = 'active';
        $user2->setPassword($password);
        $user2->save();
        
        $session1 = $this->service->login('e2e-test-user', $password);
        $session2 = $this->service->login('e2e-test-user-2', $password);
        
        $this->assertNotEquals($session1['token'], $session2['token']);
        
        $this->assertEquals('e2e-test-user', $this->service->validateToken($session1['token'])?->username);
        $this->assertEquals('e2e-test-user-2', $this->service->validateToken($session2['token'])?->username);
    }

    public function testLoginFailureIncrementSecurity(): void
    {
        $user = new User();
        $user->username = 'e2e-test-user';
        $user->status = 'active';
        $user->setPassword('correctpassword');
        $user->save();

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->service->login('e2e-test-user', 'wrongpassword');
            } catch (\Exception $e) {
            }
        }

        $user->refresh();
        
        $this->expectException(\Exception::class);
        $this->service->login('e2e-test-user', 'wrongpassword');
    }

    public function testAccountRecoveryAfterLockout(): void
    {
        $user = new User();
        $user->username = 'e2e-test-user';
        $user->status = 'active';
        $user->setPassword('password123');
        $user->failed_attempts = 5;
        $user->locked_until = date('Y-m-d H:i:s', time() + 3600);
        $user->save();

        $this->service->unlockAccount($user->id);
        
        $user->refresh();
        
        $this->assertFalse($user->isLocked());
        $this->assertEquals(0, $user->failed_attempts);
    }

    public function testRoleBasedAccessInSession(): void
    {
        // Clean up any leftovers from a previous run before creating
        User::where('username', 'e2e-test-admin')->delete();

        $adminUser = new User();
        $adminUser->username = 'e2e-test-admin';
        $adminUser->role = 'administrator';
        $adminUser->status = 'active';
        $adminUser->setPassword('adminpass123');
        $adminUser->save();

        $clientUser = new User();
        $clientUser->username = 'e2e-test-user';
        $clientUser->role = 'regular_user';
        $clientUser->status = 'active';
        $clientUser->setPassword('clientpass123');
        $clientUser->save();

        $adminSession = $this->service->login('e2e-test-admin', 'adminpass123');
        $clientSession = $this->service->login('e2e-test-user', 'clientpass123');

        // administrator should have users.read; regular_user should not
        $this->assertTrue($adminSession['user']->hasPermission('users.read'));
        $this->assertFalse($clientSession['user']->hasPermission('users.read'));

        // Tear down both users created in this test
        User::where('username', 'e2e-test-admin')->delete();
        User::where('username', 'e2e-test-user')->delete();
    }
}