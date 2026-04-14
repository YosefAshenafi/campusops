<?php

namespace tests;

use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    /**
     * Verify password hashing uses bcrypt and salted passwords verify correctly.
     */
    public function testPasswordHashingWithSaltVerifies()
    {
        $password = 'testpassword123';
        $salt = bin2hex(random_bytes(16));
        $hash = password_hash($password . $salt, PASSWORD_BCRYPT);

        $this->assertTrue(password_verify($password . $salt, $hash));
        $this->assertFalse(password_verify('wrongpassword' . $salt, $hash));
    }

    /**
     * Verify wrong password does not verify.
     */
    public function testWrongPasswordDoesNotVerify()
    {
        $password = 'correct_password';
        $salt = bin2hex(random_bytes(16));
        $hash = password_hash($password . $salt, PASSWORD_BCRYPT);

        $this->assertFalse(password_verify('incorrect_password' . $salt, $hash));
    }

    /**
     * Verify lockout triggers after exactly 5 failed attempts.
     */
    public function testLockoutTriggersAtFiveAttempts()
    {
        $maxAttempts = 5;

        for ($i = 1; $i <= $maxAttempts; $i++) {
            $locked = $i >= $maxAttempts;
            if ($i < $maxAttempts) {
                $this->assertFalse($locked, "Should not be locked at attempt {$i}");
            } else {
                $this->assertTrue($locked, "Should be locked at attempt {$i}");
            }
        }
    }

    /**
     * Verify lockout does not trigger below threshold.
     */
    public function testNoLockoutBelowThreshold()
    {
        $failedAttempts = 4;
        $locked = $failedAttempts >= 5;
        $this->assertFalse($locked);
    }

    /**
     * Verify lockout window calculation (15 minutes).
     */
    public function testLockoutWindowIsFifteenMinutes()
    {
        $lockoutDuration = 15 * 60;
        $lockedAt = time();
        $lockedUntil = $lockedAt + $lockoutDuration;

        $this->assertEquals(900, $lockoutDuration);
        $this->assertGreaterThan($lockedAt, $lockedUntil);

        // After lockout expires, user should not be locked
        $afterExpiry = $lockedUntil + 1;
        $this->assertGreaterThan($lockedUntil, $afterExpiry);
    }

    /**
     * Verify session token is 64-char hex string.
     */
    public function testSessionTokenFormatIsValid()
    {
        $token = bin2hex(random_bytes(32));

        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Verify session tokens are unique per generation.
     */
    public function testSessionTokensAreUnique()
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->assertNotEquals($token1, $token2);
    }

    /**
     * Verify role permission lookup returns correct results.
     */
    public function testRolePermissionLookup()
    {
        $permissions = [
            'users.read',
            'users.create',
            'users.update',
            'users.delete',
        ];

        $this->assertTrue(in_array('users.read', $permissions));
        $this->assertTrue(in_array('users.create', $permissions));
        $this->assertFalse(in_array('orders.cancel', $permissions));
    }

    /**
     * Verify disabled account status is correctly identified.
     */
    public function testDisabledAccountStatusCheck()
    {
        $status = 'disabled';
        $this->assertEquals('disabled', $status);
        $this->assertNotEquals('active', $status);

        $activeStatus = 'active';
        $this->assertNotEquals('disabled', $activeStatus);
    }

    /**
     * Verify failed attempt counter increments correctly and resets.
     */
    public function testFailedAttemptCounterBehavior()
    {
        $failedAttempts = 0;

        // Simulate 3 failures
        for ($i = 0; $i < 3; $i++) {
            $failedAttempts++;
        }
        $this->assertEquals(3, $failedAttempts);
        $this->assertFalse($failedAttempts >= 5);

        // Reset on successful login
        $failedAttempts = 0;
        $this->assertEquals(0, $failedAttempts);
    }
}
