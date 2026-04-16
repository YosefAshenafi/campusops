<?php

declare(strict_types=1);

namespace tests\middleware;

use app\middleware\SensitiveDataMiddleware;
use app\model\User;
use PHPUnit\Framework\TestCase;
use think\Request;
use think\Response;

/**
 * Unit tests for SensitiveDataMiddleware.
 *
 * Tests the masking logic for user and order entity types, and verifies that
 * administrator users bypass masking entirely.
 */
class SensitiveDataMiddlewareTest extends TestCase
{
    private SensitiveDataMiddleware $mw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mw = new SensitiveDataMiddleware();
    }

    // ------------------------------------------------------------------
    // detectEntityType (via reflection)
    // ------------------------------------------------------------------

    public function testDetectEntityTypeReturnsUserForUserPath(): void
    {
        $req = new Request();
        $req->setPathinfo('api/v1/users/1');

        $type = $this->callDetect($req);
        $this->assertSame('user', $type);
    }

    public function testDetectEntityTypeReturnsOrderForOrderPath(): void
    {
        $req = new Request();
        $req->setPathinfo('api/v1/orders/5');

        $type = $this->callDetect($req);
        $this->assertSame('order', $type);
    }

    public function testDetectEntityTypeReturnsEmptyForOtherPaths(): void
    {
        $req = new Request();
        $req->setPathinfo('api/v1/activities/1');

        $type = $this->callDetect($req);
        $this->assertSame('', $type);
    }

    // ------------------------------------------------------------------
    // maskFields (via reflection)
    // ------------------------------------------------------------------

    public function testMaskFieldsRedactsPasswordHash(): void
    {
        $data = ['id' => 1, 'username' => 'alice', 'password_hash' => 'secret'];

        $masked = $this->callMaskFields($data, ['password_hash', 'salt']);

        $this->assertSame('***REDACTED***', $masked['password_hash']);
        $this->assertSame('alice', $masked['username']);
    }

    public function testMaskFieldsRedactsSalt(): void
    {
        $data = ['id' => 1, 'salt' => 'abc123'];

        $masked = $this->callMaskFields($data, ['password_hash', 'salt']);

        $this->assertSame('***REDACTED***', $masked['salt']);
    }

    public function testMaskFieldsRedactsInvoiceAddress(): void
    {
        $data = ['id' => 2, 'invoice_address' => '123 Main St'];

        $masked = $this->callMaskFields($data, ['invoice_address']);

        $this->assertSame('***REDACTED***', $masked['invoice_address']);
    }

    public function testMaskFieldsLeavesNullFieldsUntouched(): void
    {
        $data = ['id' => 1, 'invoice_address' => null, 'amount' => 100];

        $masked = $this->callMaskFields($data, ['invoice_address']);

        $this->assertNull($masked['invoice_address']);
    }

    public function testMaskFieldsLeavesEmptyStringUntouched(): void
    {
        $data = ['id' => 1, 'salt' => ''];

        $masked = $this->callMaskFields($data, ['password_hash', 'salt']);

        $this->assertSame('', $masked['salt']);
    }

    // ------------------------------------------------------------------
    // maskRecursive — list payload
    // ------------------------------------------------------------------

    public function testMaskRecursiveHandlesListPayload(): void
    {
        $data = [
            'list' => [
                ['id' => 1, 'password_hash' => 'h1', 'salt' => 's1'],
                ['id' => 2, 'password_hash' => 'h2', 'salt' => 's2'],
            ],
        ];

        $result = $this->callMaskRecursive($data, 'user');

        $this->assertSame('***REDACTED***', $result['list'][0]['password_hash']);
        $this->assertSame('***REDACTED***', $result['list'][1]['salt']);
    }

    public function testMaskRecursiveHandlesSingleEntity(): void
    {
        $data = ['id' => 3, 'invoice_address' => '99 Elm Ave', 'amount' => 50.0];

        $result = $this->callMaskRecursive($data, 'order');

        $this->assertSame('***REDACTED***', $result['invoice_address']);
        $this->assertSame(50.0, $result['amount']);
    }

    // ------------------------------------------------------------------
    // handle() — admin bypass
    // ------------------------------------------------------------------

    public function testHandleDoesNotMaskForAdminUser(): void
    {
        $req = new Request();
        $req->setPathinfo('api/v1/users/1');

        // Attach an admin user stub to the request.
        $req->user        = new \stdClass();
        $req->user->role  = 'administrator';

        $responseBody = json_encode([
            'success' => true,
            'code'    => 200,
            'data'    => ['id' => 1, 'password_hash' => 'secret_hash', 'salt' => 'my_salt'],
        ]);

        $next = function (Request $r) use ($responseBody): Response {
            return json(json_decode($responseBody, true));
        };

        $response = $this->mw->handle($req, $next);
        $body     = json_decode($response->getContent(), true);

        // Admin data must NOT be redacted.
        $this->assertSame('secret_hash', $body['data']['password_hash']);
        $this->assertSame('my_salt', $body['data']['salt']);
    }

    // ------------------------------------------------------------------
    // handle() — non-admin masking
    // ------------------------------------------------------------------

    public function testHandleMasksUserFieldsForNonAdmin(): void
    {
        $req = new Request();
        $req->setPathinfo('api/v1/users/1');

        $req->user       = new \stdClass();
        $req->user->role = 'regular_user';

        $responseBody = json_encode([
            'success' => true,
            'code'    => 200,
            'data'    => ['id' => 1, 'username' => 'alice', 'password_hash' => 'secret', 'salt' => 'mysalt'],
        ]);

        $next = function (Request $r) use ($responseBody): Response {
            return json(json_decode($responseBody, true));
        };

        $response = $this->mw->handle($req, $next);
        $body     = json_decode($response->getContent(), true);

        $this->assertSame('***REDACTED***', $body['data']['password_hash']);
        $this->assertSame('***REDACTED***', $body['data']['salt']);
        $this->assertSame('alice', $body['data']['username']);
    }

    public function testHandleMasksOrderFieldsForNonAdmin(): void
    {
        $req = new Request();
        $req->setPathinfo('api/v1/orders/2');

        $req->user       = new \stdClass();
        $req->user->role = 'operations_staff';

        $responseBody = json_encode([
            'success' => true,
            'code'    => 200,
            'data'    => ['id' => 2, 'invoice_address' => '123 Main St', 'amount' => 99.0],
        ]);

        $next = function (Request $r) use ($responseBody): Response {
            return json(json_decode($responseBody, true));
        };

        $response = $this->mw->handle($req, $next);
        $body     = json_decode($response->getContent(), true);

        $this->assertSame('***REDACTED***', $body['data']['invoice_address']);
        $this->assertSame(99.0, $body['data']['amount']);
    }

    public function testHandlePassesThroughWhenNoUserAttached(): void
    {
        $req = new Request();
        $req->setPathinfo('api/v1/users/1');
        // No $req->user set — null check: middleware should pass through.

        $called = false;
        $next   = function (Request $r) use (&$called): Response {
            $called = true;
            return json(['success' => true, 'data' => ['password_hash' => 'raw']]);
        };

        $this->mw->handle($req, $next);
        $this->assertTrue($called);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function callDetect(Request $req): string
    {
        $ref = new \ReflectionMethod($this->mw, 'detectEntityType');
        $ref->setAccessible(true);
        return $ref->invoke($this->mw, $req);
    }

    private function callMaskFields(array $data, array $fields): array
    {
        $ref = new \ReflectionMethod($this->mw, 'maskFields');
        $ref->setAccessible(true);
        return $ref->invoke($this->mw, $data, $fields);
    }

    private function callMaskRecursive(array $data, string $entityType): array
    {
        $ref = new \ReflectionMethod($this->mw, 'maskRecursive');
        $ref->setAccessible(true);
        return $ref->invoke($this->mw, $data, $entityType);
    }
}
