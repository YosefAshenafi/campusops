<?php

namespace app\service;

use app\model\User;
use app\model\Session;

class AuthService
{
    /**
     * Authenticate a user with username and password.
     *
     * @return array{user: User, token: string, expires_at: string}
     * @throws \Exception on failure
     */
    public function login(string $username, string $password): array
    {
        $user = User::where('username', $username)->find();

        if (!$user) {
            \think\facade\Log::warning("Auth failure: unknown username attempt");
            throw new \Exception('Invalid credentials', 401);
        }

        if ($user->status === 'disabled') {
            \think\facade\Log::warning("Auth failure: disabled account for user_id {$user->id}");
            throw new \Exception('Account is disabled', 403);
        }

        if ($user->isLocked()) {
            $remaining = strtotime($user->locked_until) - time();
            $minutes = ceil($remaining / 60);
            \think\facade\Log::warning("Auth failure: locked account for user_id {$user->id}");
            throw new \Exception("Account locked. Try again in {$minutes} minute(s)", 429);
        }

        if (!$user->verifyPassword($password)) {
            $user->recordFailedAttempt();
            \think\facade\Log::warning("Auth failure: bad password for user_id {$user->id}, attempts: {$user->failed_attempts}");

            if ($user->isLocked()) {
                \think\facade\Log::error("Auth lockout: user_id {$user->id} locked after 5 failed attempts");
                throw new \Exception('Account locked after 5 failed attempts. Try again in 15 minutes', 429);
            }

            $attemptsLeft = 5 - $user->failed_attempts;
            throw new \Exception("Invalid credentials. {$attemptsLeft} attempt(s) remaining", 401);
        }

        // Successful login
        $user->resetFailedAttempts();
        \think\facade\Log::info("Auth success: user_id {$user->id} logged in");

        // Create session
        $session = Session::createForUser($user->id);

        return [
            'user' => $user,
            'token' => $session->token,
            'expires_at' => $session->expires_at,
        ];
    }

    /**
     * Logout by invalidating the session token.
     */
    public function logout(string $token): void
    {
        $session = Session::where('token', $token)->find();
        if ($session) {
            $session->invalidate();
        }
    }

    /**
     * Unlock a user account (admin action).
     */
    public function unlockAccount(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found', 404);
        }
        $user->failed_attempts = 0;
        $user->locked_until = null;
        $user->save();
    }

    /**
     * Validate a token and return the associated user.
     */
    public function validateToken(string $token): ?User
    {
        $session = Session::findValidByToken($token);
        if (!$session) {
            return null;
        }
        return User::find($session->user_id);
    }
}
