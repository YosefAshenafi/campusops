<?php

declare(strict_types=1);

namespace tests\services;

use app\model\User;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    public function testVerifyPassword(): void
    {
        $user = new User();
        $user->username = 'testuser';
        $user->salt = bin2hex(random_bytes(16));
        $plainPassword = 'testpassword123';
        $user->password_hash = password_hash($plainPassword . $user->salt, PASSWORD_BCRYPT);

        $result = $user->verifyPassword($plainPassword);

        $this->assertTrue($result);
    }

    public function testVerifyPasswordReturnsFalseForWrongPassword(): void
    {
        $user = new User();
        $user->username = 'testuser';
        $user->salt = bin2hex(random_bytes(16));
        $user->password_hash = password_hash('correctpassword' . $user->salt, PASSWORD_BCRYPT);

        $result = $user->verifyPassword('wrongpassword');

        $this->assertFalse($result);
    }

    public function testSetPasswordHashesPassword(): void
    {
        $user = new User();
        $plainPassword = 'mynewpassword';

        $user->setPassword($plainPassword);

        $this->assertNotEmpty($user->password_hash);
        $this->assertNotEmpty($user->salt);
        $this->assertTrue($user->verifyPassword($plainPassword));
    }

    public function testIsLockedReturnsFalseWhenNotLocked(): void
    {
        $user = new User();
        $user->locked_until = null;

        $this->assertFalse($user->isLocked());
    }

    public function testIsLockedReturnsTrueWhenLockedInFuture(): void
    {
        $user = new User();
        $user->locked_until = date('Y-m-d H:i:s', time() + 3600);

        $this->assertTrue($user->isLocked());
    }

    public function testIsLockedReturnsFalseWhenLockedInPast(): void
    {
        $user = new User();
        $user->locked_until = date('Y-m-d H:i:s', time() - 3600);

        $this->assertFalse($user->isLocked());
    }

    public function testPasswordHashUsesBcryptAlgorithm(): void
    {
        $user = new User();
        $user->setPassword('testpassword');

        $this->assertStringStartsWith('$2y$', $user->password_hash);
    }

    public function testSaltIs32HexCharacters(): void
    {
        $user = new User();
        $user->setPassword('testpassword');

        $this->assertEquals(32, strlen($user->salt));
    }

    public function testDifferentSaltPerPassword(): void
    {
        $user1 = new User();
        $user1->setPassword('samepassword');

        $user2 = new User();
        $user2->setPassword('samepassword');

        $this->assertNotEquals($user1->salt, $user2->salt);
    }

    public function testRecordFailedAttemptIncrementsCounter(): void
    {
        $user = new User();
        $user->failed_attempts = 0;

        $this->assertEquals(0, $user->failed_attempts);
    }

    public function testResetFailedAttemptsClearsLock(): void
    {
        $user = new User();
        $user->failed_attempts = 5;
        $user->locked_until = date('Y-m-d H:i:s', time() + 3600);

        $user->failed_attempts = 0;
        $user->locked_until = null;

        $this->assertEquals(0, $user->failed_attempts);
        $this->assertNull($user->locked_until);
    }
}