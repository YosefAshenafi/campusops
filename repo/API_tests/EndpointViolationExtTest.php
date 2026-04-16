<?php

declare(strict_types=1);

namespace tests\api;

use app\model\Violation;
use app\model\ViolationRule;

/**
 * HTTP endpoint tests for violation routes not covered by EndpointViolationTest:
 *   GET    /api/v1/violations/rules/:id
 *   PUT    /api/v1/violations/rules/:id
 *   DELETE /api/v1/violations/rules/:id
 *   GET    /api/v1/violations/user/:user_id
 *   GET    /api/v1/violations/group/:group_id
 *   POST   /api/v1/violations/:id/final-decision
 *
 * Bootstrap roles used:
 *   administrator  — violations.* (all violation permissions)
 *   regular_user   — no violations permissions
 */
class EndpointViolationExtTest extends HttpTestCase
{
    private ViolationRule $rule;
    private Violation     $violation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupUsersLike('http-violext-%');
        $this->cleanupUsersLike('http-test-admin%');
        $this->cleanupTestData();

        $this->rule      = $this->createRule();
        $this->violation = $this->createViolation($this->rule->id);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        $this->cleanupUsersLike('http-violext-%');
        $this->cleanupUsersLike('http-test-admin%');
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // GET /api/v1/violations/rules/:id  (rbac: violations.read)
    // ------------------------------------------------------------------

    public function testGetRuleReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/violations/rules/' . $this->rule->id);
        $this->assertUnauthorized($res);
    }

    public function testGetRuleReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-violext-regular');
        $res = $this->get('/api/v1/violations/rules/' . $this->rule->id);
        $this->assertForbidden($res);
    }

    public function testGetRuleAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/violations/rules/' . $this->rule->id);
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/violations/rules/:id  (rbac: violations.rules)
    // ------------------------------------------------------------------

    public function testUpdateRuleReturns401WhenUnauthenticated(): void
    {
        $res = $this->put('/api/v1/violations/rules/' . $this->rule->id, [
            'points' => 10,
        ]);
        $this->assertUnauthorized($res);
    }

    public function testUpdateRuleReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-violext-regular');
        $res = $this->put('/api/v1/violations/rules/' . $this->rule->id, [
            'points' => 10,
        ]);
        $this->assertForbidden($res);
    }

    public function testUpdateRuleAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        $res = $this->put('/api/v1/violations/rules/' . $this->rule->id, [
            'points' => 10,
        ]);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // DELETE /api/v1/violations/rules/:id  (rbac: violations.rules)
    // ------------------------------------------------------------------

    public function testDeleteRuleReturns401WhenUnauthenticated(): void
    {
        $res = $this->delete('/api/v1/violations/rules/' . $this->rule->id);
        $this->assertUnauthorized($res);
    }

    public function testDeleteRuleReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-violext-regular');
        $res = $this->delete('/api/v1/violations/rules/' . $this->rule->id);
        $this->assertForbidden($res);
    }

    public function testDeleteRuleAdminCanReachEndpoint(): void
    {
        // Create a separate rule so this test does not destroy the shared fixture
        $disposableRule = $this->createRule('http-violext-rule-delete-');
        $this->loginAsAdmin();
        $res = $this->delete('/api/v1/violations/rules/' . $disposableRule->id);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // GET /api/v1/violations/user/:user_id  (rbac: violations.read)
    // ------------------------------------------------------------------

    public function testGetViolationsByUserReturns401WhenUnauthenticated(): void
    {
        $user = $this->ensureUser('http-violext-target', 'regular_user');
        $res  = $this->get('/api/v1/violations/user/' . $user->id);
        $this->assertUnauthorized($res);
    }

    public function testGetViolationsByUserReturns403ForRegularUser(): void
    {
        $target = $this->ensureUser('http-violext-target', 'regular_user');
        $this->loginAsRole('regular_user', 'http-violext-regular');
        $res = $this->get('/api/v1/violations/user/' . $target->id);
        $this->assertForbidden($res);
    }

    public function testGetViolationsByUserAdminCanReachEndpoint(): void
    {
        $target = $this->ensureUser('http-violext-target', 'regular_user');
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/violations/user/' . $target->id);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // GET /api/v1/violations/group/:group_id  (rbac: violations.read)
    // ------------------------------------------------------------------

    public function testGetViolationsByGroupReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/violations/group/1');
        $this->assertUnauthorized($res);
    }

    public function testGetViolationsByGroupReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-violext-regular');
        $res = $this->get('/api/v1/violations/group/1');
        $this->assertForbidden($res);
    }

    public function testGetViolationsByGroupAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        // group_id=1 may return an empty list; any non-401/non-403 response is acceptable
        $res = $this->get('/api/v1/violations/group/1');
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // POST /api/v1/violations/:id/final-decision  (rbac: violations.review)
    // ------------------------------------------------------------------

    public function testFinalDecisionReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/violations/' . $this->violation->id . '/final-decision', [
            'decision' => 'upheld',
        ]);
        $this->assertUnauthorized($res);
    }

    public function testFinalDecisionReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-violext-regular');
        $res = $this->post('/api/v1/violations/' . $this->violation->id . '/final-decision', [
            'decision' => 'upheld',
        ]);
        $this->assertForbidden($res);
    }

    public function testFinalDecisionAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        // The service may return 400/422 when the violation is not in the correct
        // state for a final-decision; what matters is that auth passes.
        $res = $this->post('/api/v1/violations/' . $this->violation->id . '/final-decision', [
            'decision' => 'upheld',
        ]);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createRule(string $namePrefix = 'http-violext-rule-'): ViolationRule
    {
        $rule           = new ViolationRule();
        $rule->name     = $namePrefix . uniqid();
        $rule->points   = 5;
        $rule->category = 'general';
        $rule->created_by = 1;
        $rule->save();
        return $rule;
    }

    private function createViolation(int $ruleId): Violation
    {
        $v             = new Violation();
        $v->user_id    = 1;
        $v->rule_id    = $ruleId;
        $v->points     = 5;
        $v->notes      = 'http-violext-test-note';
        $v->status     = 'pending';
        $v->created_by = 1;
        $v->save();
        return $v;
    }

    private function cleanupTestData(): void
    {
        Violation::where('notes', 'like', 'http-violext%')->delete();
        ViolationRule::where('name', 'like', 'http-violext-rule-%')->delete();
    }
}
