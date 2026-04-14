<?php

declare(strict_types=1);

namespace tests\api;

use app\middleware\AuthMiddleware;
use app\middleware\RbacMiddleware;
use app\model\User;
use app\model\Session;
use app\service\AuthService;
use PHPUnit\Framework\TestCase;
use think\Request;
use think\Response;

/**
 * Tests that verify the AuthMiddleware and RbacMiddleware classes enforce
 * the correct HTTP status codes at the middleware layer — not just at the
 * service layer.  These replace the service-only permission assertions
 * that previously left route-level enforcement untested.
 */
class HttpMiddlewareTest extends TestCase
{
    private AuthMiddleware $authMiddleware;
    private RbacMiddleware $rbacMiddleware;
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authMiddleware = new AuthMiddleware();
        $this->rbacMiddleware = new RbacMiddleware();
        $this->authService    = new AuthService();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // AuthMiddleware — unauthenticated requests
    // ------------------------------------------------------------------

    public function testAuthMiddlewareReturns401WithNoAuthorizationHeader(): void
    {
        $request = new Request();
        $response = $this->authMiddleware->handle($request, $this->passThroughNext());

        $this->assertSame(401, $response->getCode());
        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['success']);
    }

    public function testAuthMiddlewareReturns401WithMalformedHeader(): void
    {
        $request = new Request();
        $request->withHeader(['Authorization' => 'Basic dXNlcjpwYXNz']);

        $response = $this->authMiddleware->handle($request, $this->passThroughNext());

        $this->assertSame(401, $response->getCode());
    }

    public function testAuthMiddlewareReturns401WithInvalidBearerToken(): void
    {
        $request = new Request();
        $request->withHeader(['Authorization' => 'Bearer invalid-token-' . uniqid()]);

        $response = $this->authMiddleware->handle($request, $this->passThroughNext());

        $this->assertSame(401, $response->getCode());
    }

    public function testAuthMiddlewareReturns403ForInactiveUser(): void
    {
        $user = $this->createUser('mw-inactive', 'disabled');
        $session = Session::createForUser($user->id);

        $request = new Request();
        $request->withHeader(['Authorization' => 'Bearer ' . $session->token]);

        $response = $this->authMiddleware->handle($request, $this->passThroughNext());

        $this->assertSame(403, $response->getCode());
    }

    public function testAuthMiddlewarePassesThroughForValidActiveToken(): void
    {
        $user = $this->createUser('mw-active', 'active');
        $session = Session::createForUser($user->id);

        $request = new Request();
        $request->withHeader(['Authorization' => 'Bearer ' . $session->token]);

        $called = false;
        $next = function (Request $req) use (&$called, $user): Response {
            $called = true;
            $this->assertNotNull($req->user, 'AuthMiddleware must attach user to request');
            $this->assertSame($user->id, $req->user->id);
            return json(['success' => true], 200);
        };

        $response = $this->authMiddleware->handle($request, $next);

        $this->assertTrue($called, 'next() must be called for valid token');
        $this->assertSame(200, $response->getCode());
    }

    // ------------------------------------------------------------------
    // RbacMiddleware — authorization checks
    // ------------------------------------------------------------------

    public function testRbacMiddlewareReturns401WhenNoUserAttachedToRequest(): void
    {
        $request = new Request();
        // No $request->user set — simulates AuthMiddleware not having run

        $response = $this->rbacMiddleware->handle($request, $this->passThroughNext(), 'users.read');

        $this->assertSame(401, $response->getCode());
    }

    public function testRbacMiddlewareReturns403WhenUserLacksPermission(): void
    {
        $user = $this->createUser('mw-regular', 'active', 'regular_user');
        $request = new Request();
        $request->user = $user;

        $response = $this->rbacMiddleware->handle($request, $this->passThroughNext(), 'users.read');

        $this->assertSame(403, $response->getCode());
        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Insufficient permissions', $body['error']);
    }

    public function testRbacMiddlewarePassesThroughWhenUserHasPermission(): void
    {
        $user = $this->createUser('mw-admin', 'active', 'administrator');
        $request = new Request();
        $request->user = $user;

        $called = false;
        $next = function (Request $req) use (&$called): Response {
            $called = true;
            return json(['success' => true], 200);
        };

        $response = $this->rbacMiddleware->handle($request, $next, 'users.read');

        $this->assertTrue($called);
        $this->assertSame(200, $response->getCode());
    }

    public function testRbacMiddlewarePassesThroughWithNoPermissionRequired(): void
    {
        $user = $this->createUser('mw-regular-noperm', 'active', 'regular_user');
        $request = new Request();
        $request->user = $user;

        $called = false;
        $next = function (Request $req) use (&$called): Response {
            $called = true;
            return json(['success' => true], 200);
        };

        // Empty permission string means "authenticated only, no specific permission"
        $response = $this->rbacMiddleware->handle($request, $next, '');

        $this->assertTrue($called);
        $this->assertSame(200, $response->getCode());
    }

    public function testOperationsStaffCannotAccessUsersRead(): void
    {
        $user = $this->createUser('mw-ops', 'active', 'operations_staff');
        $request = new Request();
        $request->user = $user;

        $response = $this->rbacMiddleware->handle($request, $this->passThroughNext(), 'users.read');

        $this->assertSame(403, $response->getCode());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function passThroughNext(): \Closure
    {
        return function (Request $req): Response {
            return json(['success' => true], 200);
        };
    }

    private function createUser(string $username, string $status, string $role = 'regular_user'): User
    {
        $user = User::where('username', $username)->find();
        if (!$user) {
            $user = new User();
            $user->username = $username;
        }
        $user->role   = $role;
        $user->status = $status;
        $user->setPassword('MiddlewareTest1!');
        $user->save();
        return $user;
    }

    private function cleanUp(): void
    {
        foreach (['mw-inactive','mw-active','mw-regular','mw-admin','mw-regular-noperm','mw-ops'] as $u) {
            User::where('username', $u)->delete();
        }
    }
}
