<?php

declare(strict_types=1);

namespace tests\middleware;

use app\middleware\RateLimitMiddleware;
use PHPUnit\Framework\TestCase;
use think\Request;
use think\Response;

/**
 * Unit tests for RateLimitMiddleware.
 *
 * The bootstrap puts RATE_LIMIT_BYPASS=1 so all endpoint tests avoid the
 * quota. This file tests the middleware class directly by temporarily
 * removing the bypass and exercising getCount / setCount via reflection.
 */
class RateLimitMiddlewareTest extends TestCase
{
    private RateLimitMiddleware $mw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mw = new RateLimitMiddleware();
    }

    // ------------------------------------------------------------------
    // Bypass behaviour
    // ------------------------------------------------------------------

    public function testBypassEnvVarAllowsRequestThrough(): void
    {
        // bootstrap.php sets RATE_LIMIT_BYPASS=1 — handle() must call $next.
        $called = false;
        $req    = new Request();
        $next   = function (Request $r) use (&$called): Response {
            $called = true;
            return json(['ok' => true]);
        };

        $this->mw->handle($req, $next);

        $this->assertTrue($called, 'Expected $next to be called when RATE_LIMIT_BYPASS=1');
    }

    // ------------------------------------------------------------------
    // getCount / setCount round-trip (via reflection)
    // ------------------------------------------------------------------

    public function testGetCountReturnsZeroForUnknownKey(): void
    {
        $count = $this->invokeGetCount('nonexistent-key-' . uniqid());
        $this->assertSame(0, $count);
    }

    public function testSetAndGetCountRoundTrip(): void
    {
        $key = 'unit-test-rate-limit-' . uniqid();
        $this->invokeSetCount($key, 5);
        $count = $this->invokeGetCount($key);
        $this->assertSame(5, $count);
        $this->cleanKey($key);
    }

    public function testGetCountReturnsZeroAfterExpiry(): void
    {
        // Write a file with an already-expired timestamp.
        $key  = 'unit-test-expired-' . uniqid();
        $path = $this->storagePath() . '/' . $key;
        file_put_contents($path, json_encode([
            'count'      => 99,
            'expires_at' => time() - 3600,  // one hour in the past
        ]));

        $count = $this->invokeGetCount($key);

        $this->assertSame(0, $count);
        // The expired file should have been deleted.
        $this->assertFileDoesNotExist($path);
    }

    // ------------------------------------------------------------------
    // Rate limiting (bypass disabled)
    // ------------------------------------------------------------------

    public function testHandleReturns429WhenLimitExceeded(): void
    {
        // Temporarily clear the bypass so the middleware enforces limits.
        putenv('RATE_LIMIT_BYPASS=0');

        try {
            $req = new Request();
            $req->withServer([
                'REMOTE_ADDR'    => '192.0.2.99',
                'REQUEST_METHOD' => 'GET',
            ]);

            $window = date('YmdHi');
            $key    = md5('192.0.2.99:' . $window);

            // Pre-load the counter above the 60-request limit.
            $this->invokeSetCount($key, 61);

            $called = false;
            $next   = function (Request $r) use (&$called): Response {
                $called = true;
                return json(['ok' => true]);
            };

            $response = $this->mw->handle($req, $next);

            $this->assertFalse($called, '$next should NOT be called when limit exceeded');
            $this->assertSame(429, $response->getCode());

            $body = json_decode($response->getContent(), true);
            $this->assertFalse($body['success'] ?? true);
            $this->assertSame(429, $body['code'] ?? 0);

            $this->cleanKey($key);
        } finally {
            putenv('RATE_LIMIT_BYPASS=1');
        }
    }

    public function testHandleCallsNextWhenUnderLimit(): void
    {
        putenv('RATE_LIMIT_BYPASS=0');

        try {
            $req = new Request();
            $req->withServer([
                'REMOTE_ADDR'    => '192.0.2.88',
                'REQUEST_METHOD' => 'GET',
            ]);

            $window = date('YmdHi');
            $key    = md5('192.0.2.88:' . $window);

            // Start at 1 — well within the limit.
            $this->invokeSetCount($key, 1);

            $called = false;
            $next   = function (Request $r) use (&$called): Response {
                $called = true;
                return json(['ok' => true]);
            };

            $this->mw->handle($req, $next);

            $this->assertTrue($called, '$next should be called when under limit');
            $this->cleanKey($key);
        } finally {
            putenv('RATE_LIMIT_BYPASS=1');
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function storagePath(): string
    {
        $ref = new \ReflectionMethod($this->mw, 'getStoragePath');
        $ref->setAccessible(true);
        return $ref->invoke($this->mw);
    }

    private function invokeGetCount(string $key): int
    {
        $ref = new \ReflectionMethod($this->mw, 'getCount');
        $ref->setAccessible(true);
        return $ref->invoke($this->mw, $key);
    }

    private function invokeSetCount(string $key, int $count): void
    {
        $ref = new \ReflectionMethod($this->mw, 'setCount');
        $ref->setAccessible(true);
        $ref->invoke($this->mw, $key, $count);
    }

    private function cleanKey(string $key): void
    {
        $file = $this->storagePath() . '/' . $key;
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
