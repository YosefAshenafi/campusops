<?php

declare(strict_types=1);

namespace tests\services;

use app\model\User;
use app\model\ViolationRule;
use app\model\Violation;
use app\service\ViolationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ViolationService object-level authorization.
 */
class ViolationServiceTest extends TestCase
{
    private ViolationService $service;
    private static int $ruleId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ViolationService();
        $this->cleanUp();

        // Ensure a violation rule exists to attach violations to
        $rule = ViolationRule::where('name', 'test-rule-unit')->find();
        if (!$rule) {
            $rule = new ViolationRule();
            $rule->name     = 'test-rule-unit';
            $rule->points   = 5;
            $rule->category = 'general';
            $rule->created_by = 1;
            $rule->save();
        }
        self::$ruleId = $rule->id;
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // listViolations — regular_user sees only their own
    // ------------------------------------------------------------------

    public function testListViolationsForRegularUserReturnsOnlyOwnViolations(): void
    {
        // Create violations for user 5 and user 6
        $this->createViolation(5);
        $this->createViolation(5);
        $this->createViolation(6);

        $result = $this->service->listViolations(1, 20, '', '', 5, 'regular_user');

        foreach ($result['list'] as $v) {
            $this->assertEquals(5, $v['user_id'], 'regular_user should only see violations where user_id = 5');
        }

        // Should not include violation belonging to user 6
        $this->assertCount(2, $result['list']);
    }

    public function testListViolationsForAdminReturnsAll(): void
    {
        $this->createViolation(5);
        $this->createViolation(6);

        $result = $this->service->listViolations(1, 20, '', '', 1, 'administrator');

        // Admin should see at least the 2 we just created
        $this->assertGreaterThanOrEqual(2, $result['total']);
    }

    // ------------------------------------------------------------------
    // getViolation — regular_user denied for other users' violations
    // ------------------------------------------------------------------

    public function testGetViolationThrows403ForRegularUserAccessingOthersViolation(): void
    {
        $violation = $this->createViolation(6);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        // User 5 trying to read a violation belonging to user 6
        $this->service->getViolation($violation->id, 5, 'regular_user');
    }

    public function testGetViolationSucceedsForOwner(): void
    {
        $violation = $this->createViolation(5);

        $result = $this->service->getViolation($violation->id, 5, 'regular_user');

        $this->assertEquals($violation->id, $result['id']);
        $this->assertEquals(5, $result['user_id']);
    }

    public function testGetViolationSucceedsForAdministrator(): void
    {
        $violation = $this->createViolation(6);

        // Administrator can read any violation
        $result = $this->service->getViolation($violation->id, 1, 'administrator');

        $this->assertEquals($violation->id, $result['id']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createViolation(int $userId): Violation
    {
        $v = new Violation();
        $v->user_id    = $userId;
        $v->rule_id    = self::$ruleId;
        $v->points     = 5;
        $v->notes      = 'unit-test-violation';
        $v->status     = ViolationService::STATUS_PENDING;
        $v->created_by = 1;
        $v->save();

        return $v;
    }

    private function cleanUp(): void
    {
        Violation::where('notes', 'unit-test-violation')->delete();
    }
}
