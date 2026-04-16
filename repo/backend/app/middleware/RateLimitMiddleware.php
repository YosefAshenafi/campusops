<?php

namespace app\middleware;

use think\Request;
use think\Response;

class RateLimitMiddleware
{
    protected static int $windowSeconds = 60;
    protected static int $maxRequests = 60;

    public function handle(Request $request, \Closure $next): Response
    {
        // Allow tests to bypass rate limiting via environment variable.
        if (getenv('RATE_LIMIT_BYPASS') === '1') {
            return $next($request);
        }

        $ip = $request->ip();
        $window = date('YmdHi');
        $key = md5($ip . ':' . $window);

        $count = $this->getCount($key);
        $count++;

        if ($count > self::$maxRequests) {
            return json([
                'success' => false,
                'code' => 429,
                'error' => 'Rate limit exceeded',
            ], 429);
        }

        $this->setCount($key, $count);

        return $next($request);
    }

    protected function getStoragePath(): string
    {
        $path = runtime_path() . '/rate_limit';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return $path;
    }

    protected function getCount(string $key): int
    {
        $file = $this->getStoragePath() . '/' . $key;
        if (!file_exists($file)) {
            return 0;
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return 0;
        }

        $parsed = json_decode($data, true);
        if (!$parsed || ($parsed['expires_at'] ?? 0) < time()) {
            @unlink($file);
            return 0;
        }

        return (int) ($parsed['count'] ?? 0);
    }

    protected function setCount(string $key, int $count): void
    {
        $file = $this->getStoragePath() . '/' . $key;
        $data = json_encode([
            'count' => $count,
            'expires_at' => time() + self::$windowSeconds,
        ]);
        @file_put_contents($file, $data, LOCK_EX);

        // Cleanup old files occasionally (1% chance per request)
        if (mt_rand(1, 100) === 1) {
            $this->cleanupExpired();
        }
    }

    protected function cleanupExpired(): void
    {
        $dir = $this->getStoragePath();
        $files = glob($dir . '/*');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < ($now - self::$windowSeconds * 2)) {
                @unlink($file);
            }
        }
    }
}