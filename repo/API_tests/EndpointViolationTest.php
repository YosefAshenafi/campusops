<?php

declare(strict_types=1);

namespace tests\api;

use app\model\Violation;
use app\model\ViolationRule;
use app\model\ViolationAppeal;

/**
 * HTTP endpoint tests for:
 *   GET    /api/v1/violations/rules
 *   POST   /api/v1/violations/rules
 *   GET    /api/v1/violations
 *   GET    /api/v1/violations/:id
 *   POST   /api/v1/violations
 *   POST   /api/v1/violations/:id/appeal
 *   POST   /api/v1/violations/:id/review
 *   POST   /api/v1/violations/:id/final-decision
 */
class EndpointViolationTest extends HttpTestCase
{
    private ViolationRule $rule;
    private Violation $violation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupUsersLike('http-viol-%');
        $this->cleanupUsersLike('http-test-admin%');
        $this->cleanupTestViolations();

        $this->rule      = $this->createRule();
        $this->violation = $this->createViolation($this->rule->id);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestViolations();
        $this->cleanupUsersLike('http-viol-%');
        $this->cleanupUsersLike('http-test-admin%');
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // GET /api/v1/violations/rules
    // ------------------------------------------------------------------

    public function testListRulesReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/violations/rules');
        $this->assertUnauthorized($res);
    }

    public function testListRulesReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-viol-regular');
        $res = $this->get('/api/v1/violations/rules');
        $this->assertForbidden($res);
    }

    public function testListRulesReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/violations/rules');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/violations/rules — requires violations.rules
    // ------------------------------------------------------------------

    public function testCreateRuleReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/violations/rules', [
            'name'   => 'http-viol-rule',
            'points' => 5,
        ]);
        $this->assertUnauthorized($res);
    }

    public function testCreateRuleReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-viol-regular');
        $res = $this->post('/api/v1/violations/rules', [
            'name'   => 'http-viol-rule',
            'points' => 5,
        ]);
        $this->assertForbidden($res);
    }

    public function testCreateRuleReturns201ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/violations/rules', [
            'name'        => 'http-viol-rule-' . uniqid(),
            'description' => 'test',
            'points'      => 3,
            'category'    => 'general',
        ]);

        $this->assertStatus(201, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/violations
    // ------------------------------------------------------------------

    public function testListViolationsReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/violations');
        $this->assertUnauthorized($res);
    }

    public function testListViolationsReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-viol-regular');
        $res = $this->get('/api/v1/violations');
        $this->assertForbidden($res);
    }

    public function testListViolationsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/violations');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
        $this->assertArrayHasKey('list', $res['body']['data'] ?? []);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/violations/:id
    // ------------------------------------------------------------------

    public function testGetViolationReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/violations/' . $this->violation->id);

        $this->assertStatus(200, $res);
        $data = $res['body']['data'] ?? [];
        $this->assertEquals($this->violation->id, $data['id'] ?? null);
    }

    public function testGetViolationReturns404ForNonExistentId(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/violations/999999');

        $this->assertNotFound($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/violations — requires violations.create
    // ------------------------------------------------------------------

    public function testCreateViolationReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/violations', [
            'user_id' => 1,
            'rule_id' => $this->rule->id,
        ]);
        $this->assertUnauthorized($res);
    }

    public function testCreateViolationReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-viol-regular');
        $res = $this->post('/api/v1/violations', [
            'user_id' => 1,
            'rule_id' => $this->rule->id,
        ]);
        $this->assertForbidden($res);
    }

    public function testCreateViolationReturns201ForAdmin(): void
    {
        $this->loginAsAdmin();
        $target = $this->ensureUser('http-viol-target', 'regular_user');

        $res = $this->post('/api/v1/violations', [
            'user_id' => $target->id,
            'rule_id' => $this->rule->id,
            'notes'   => 'http test violation',
        ]);

        $this->assertStatus(201, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/violations/:id/appeal — requires violations.appeal
    // ------------------------------------------------------------------

    public function testAppealViolationReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/violations/' . $this->violation->id . '/appeal', [
            'notes' => 'I appeal',
        ]);
        $this->assertUnauthorized($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/violations/:id/review — requires violations.review
    // ------------------------------------------------------------------

    public function testReviewViolationReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/violations/' . $this->violation->id . '/review', [
            'decision' => 'upheld',
        ]);
        $this->assertUnauthorized($res);
    }

    public function testReviewViolationReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-viol-regular');
        $res = $this->post('/api/v1/violations/' . $this->violation->id . '/review', [
            'decision' => 'upheld',
        ]);
        $this->assertForbidden($res);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createRule(): ViolationRule
    {
        $rule = new ViolationRule();
        $rule->name       = 'http-viol-rule-seed';
        $rule->points     = 5;
        $rule->category   = 'general';
        $rule->created_by = 1;
        $rule->save();
        return $rule;
    }

    private function createViolation(int $ruleId): Violation
    {
        $v = new Violation();
        $v->user_id    = 1;
        $v->rule_id    = $ruleId;
        $v->points     = 5;
        $v->notes      = 'http-viol-test-note';
        $v->status     = 'pending';
        $v->created_by = 1;
        $v->save();
        return $v;
    }

    private function cleanupTestViolations(): void
    {
        $vids = Violation::where('notes', 'like', 'http-viol%')->column('id');
        if (!empty($vids)) {
            ViolationAppeal::whereIn('violation_id', $vids)->delete();
        }
        Violation::where('notes', 'like', 'http-viol%')->delete();
        ViolationRule::where('name', 'like', 'http-viol-rule%')->delete();
    }
}
