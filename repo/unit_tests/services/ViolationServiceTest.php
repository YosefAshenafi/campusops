<?php

declare(strict_types=1);

namespace tests\services;

use app\model\User;
use app\model\ViolationRule;
use app\model\Violation;
use app\model\ViolationAppeal;
use app\model\UserGroupMember;
use app\model\Notification;
use app\service\ViolationService;
use PHPUnit\Framework\TestCase;

class ViolationServiceTest extends TestCase
{
    private ViolationService $service;
    private static int $ruleId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ViolationService();
        $this->cleanUp();

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
    // listViolations
    // ------------------------------------------------------------------

    public function testListViolationsForRegularUserReturnsOnlyOwnViolations(): void
    {
        $this->createViolation(5);
        $this->createViolation(5);
        $this->createViolation(6);

        $result = $this->service->listViolations(1, 20, '', '', 5, 'regular_user');

        foreach ($result['list'] as $v) {
            $this->assertEquals(5, $v['user_id']);
        }
        $this->assertCount(2, $result['list']);
    }

    public function testListViolationsForAdminReturnsAll(): void
    {
        $this->createViolation(5);
        $this->createViolation(6);

        $result = $this->service->listViolations(1, 20, '', '', 1, 'administrator');
        $this->assertGreaterThanOrEqual(2, $result['total']);
    }

    // ------------------------------------------------------------------
    // getViolation
    // ------------------------------------------------------------------

    public function testGetViolationThrows403ForRegularUserAccessingOthersViolation(): void
    {
        $violation = $this->createViolation(6);
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
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
        $result = $this->service->getViolation($violation->id, 1, 'administrator');
        $this->assertEquals($violation->id, $result['id']);
    }

    public function testGetViolationThrows404WhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->getViolation(999999, 1, 'administrator');
    }

    // ------------------------------------------------------------------
    // Rule CRUD
    // ------------------------------------------------------------------

    public function testListRulesReturnsAllRules(): void
    {
        $rules = $this->service->listRules();
        $this->assertNotEmpty($rules);
        $names = array_column($rules, 'name');
        $this->assertContains('test-rule-unit', $names);
    }

    public function testGetRuleReturnsRule(): void
    {
        $result = $this->service->getRule(self::$ruleId);
        $this->assertEquals('test-rule-unit', $result['name']);
        $this->assertEquals(5, $result['points']);
    }

    public function testGetRuleThrows404WhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->getRule(999999);
    }

    public function testCreateRuleReturnsNewRule(): void
    {
        $user = $this->mockUser('administrator');
        $result = $this->service->createRule([
            'name' => 'test-rule-created',
            'points' => 10,
            'category' => 'safety',
        ], $user);

        $this->assertEquals('test-rule-created', $result['name']);
        $this->assertEquals(10, $result['points']);
        $this->assertEquals('safety', $result['category']);

        ViolationRule::where('name', 'test-rule-created')->delete();
    }

    public function testCreateRuleThrowsWhenNameMissing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $user = $this->mockUser('administrator');
        $this->service->createRule(['points' => 10], $user);
    }

    public function testUpdateRuleModifiesFields(): void
    {
        $user = $this->mockUser('administrator');
        $result = $this->service->updateRule(self::$ruleId, ['points' => 15], $user);
        $this->assertEquals(15, $result['points']);

        // Restore
        $this->service->updateRule(self::$ruleId, ['points' => 5], $user);
    }

    public function testDeleteRuleRemovesRule(): void
    {
        $user = $this->mockUser('administrator');
        $rule = new ViolationRule();
        $rule->name = 'test-rule-delete';
        $rule->points = 1;
        $rule->category = 'general';
        $rule->created_by = 1;
        $rule->save();

        $this->service->deleteRule($rule->id, $user);
        $this->assertNull(ViolationRule::find($rule->id));
    }

    // ------------------------------------------------------------------
    // createViolation
    // ------------------------------------------------------------------

    public function testCreateViolationAssignsRulePoints(): void
    {
        $targetUser = $this->ensureUser(10, 'regular_user');
        $user = $this->mockUser('administrator');

        $result = $this->service->createViolation([
            'user_id' => $targetUser->id,
            'rule_id' => self::$ruleId,
            'notes' => 'unit-test-violation',
        ], $user);

        $this->assertEquals(5, $result['points']);
        $this->assertEquals(ViolationService::STATUS_PENDING, $result['status']);
    }

    public function testCreateViolationThrowsWhenMissingFields(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $user = $this->mockUser('administrator');
        $this->service->createViolation([], $user);
    }

    // ------------------------------------------------------------------
    // getUserViolations / getGroupViolations
    // ------------------------------------------------------------------

    public function testGetUserViolationsReturnsTotalPoints(): void
    {
        $this->createViolation(5);
        $this->createViolation(5);

        $result = $this->service->getUserViolations(5);
        $this->assertEquals(10, $result['total_points']);
        $this->assertCount(2, $result['violations']);
    }

    public function testGetUserViolationsExcludesRejected(): void
    {
        $v1 = $this->createViolation(5);
        $v2 = $this->createViolation(5);
        $v2->status = ViolationService::STATUS_REJECTED;
        $v2->save();

        $result = $this->service->getUserViolations(5);
        $this->assertEquals(5, $result['total_points']);
        $this->assertCount(1, $result['violations']);
    }

    public function testGetGroupViolationsAggregatesMemberPoints(): void
    {
        // Create a group using the model
        $group = new \app\model\UserGroup();
        $group->name = 'test-group-unit';
        $group->save();
        $groupId = $group->id;

        $m1 = new UserGroupMember();
        $m1->group_id = $groupId;
        $m1->user_id = 5;
        $m1->save();

        $m2 = new UserGroupMember();
        $m2->group_id = $groupId;
        $m2->user_id = 6;
        $m2->save();

        $this->createViolation(5);
        $this->createViolation(6);

        $result = $this->service->getGroupViolations($groupId);
        $this->assertEquals(10, $result['total_points']);
        $this->assertEquals(2, $result['member_count']);

        UserGroupMember::where('group_id', $groupId)->delete();
        $group->delete();
    }

    // ------------------------------------------------------------------
    // Appeal flow: submitAppeal / reviewAppeal / finalDecision
    // ------------------------------------------------------------------

    public function testSubmitAppealChangesStatusToUnderReview(): void
    {
        $violation = $this->createViolation(5);
        $user = $this->mockUser('regular_user', 5);

        $this->service->submitAppeal($violation->id, ['notes' => 'I disagree'], $user);

        $updated = Violation::find($violation->id);
        $this->assertEquals(ViolationService::STATUS_UNDER_REVIEW, $updated->status);

        $appeal = ViolationAppeal::where('violation_id', $violation->id)->find();
        $this->assertNotNull($appeal);
        $this->assertEquals('I disagree', $appeal->appellant_notes);
    }

    public function testReviewAppealSetsReviewerInfo(): void
    {
        $violation = $this->createViolation(5);
        $appellant = $this->mockUser('regular_user', 5);
        $this->service->submitAppeal($violation->id, ['notes' => 'appeal'], $appellant);

        $reviewer = $this->mockAdminUser();
        $this->service->reviewAppeal($violation->id, [
            'decision' => 'upheld',
            'notes' => 'Valid violation',
        ], $reviewer);

        $appeal = ViolationAppeal::where('violation_id', $violation->id)->find();
        $this->assertEquals('upheld', $appeal->decision);
        $this->assertEquals('Valid violation', $appeal->reviewer_notes);
    }

    public function testReviewAppealThrowsWithoutPermission(): void
    {
        $violation = $this->createViolation(5);
        $appellant = $this->mockUser('regular_user', 5);
        $this->service->submitAppeal($violation->id, ['notes' => 'appeal'], $appellant);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $unprivileged = $this->mockUser('regular_user', 5);
        $this->service->reviewAppeal($violation->id, ['notes' => 'test'], $unprivileged);
    }

    public function testReviewAppealThrowsWhenNotesEmpty(): void
    {
        $violation = $this->createViolation(5);
        $appellant = $this->mockUser('regular_user', 5);
        $this->service->submitAppeal($violation->id, ['notes' => 'appeal'], $appellant);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);

        $reviewer = $this->mockAdminUser();
        $this->service->reviewAppeal($violation->id, ['notes' => ''], $reviewer);
    }

    public function testFinalDecisionUpholdApprovesViolation(): void
    {
        $violation = $this->createViolation(5);
        $appellant = $this->mockUser('regular_user', 5);
        $this->service->submitAppeal($violation->id, ['notes' => 'appeal'], $appellant);

        $reviewer = $this->mockAdminUser();
        $this->service->finalDecision($violation->id, [
            'uphold' => true,
            'notes' => 'Final: upheld',
        ], $reviewer);

        $updated = Violation::find($violation->id);
        $this->assertEquals(ViolationService::STATUS_APPROVED, $updated->status);
    }

    public function testFinalDecisionRejectRejectsViolation(): void
    {
        $targetUser = $this->ensureUser(5, 'regular_user');
        $violation = $this->createViolationForUser($targetUser->id);
        $appellant = $this->mockUser('regular_user', $targetUser->id);
        $this->service->submitAppeal($violation->id, ['notes' => 'appeal'], $appellant);

        $reviewer = $this->mockAdminUser();
        $this->service->finalDecision($violation->id, [
            'uphold' => false,
            'notes' => 'Final: rejected',
        ], $reviewer);

        $updated = Violation::find($violation->id);
        $this->assertEquals(ViolationService::STATUS_REJECTED, $updated->status);
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

    private function createViolationForUser(int $userId): Violation
    {
        return $this->createViolation($userId);
    }

    private function mockUser(string $role, int $id = 1): object
    {
        return new class($role, $id) {
            public int $id;
            public string $role;
            public function __construct(string $role, int $id) {
                $this->role = $role;
                $this->id = $id;
            }
            public function hasPermission(string $permission): bool
            {
                if ($this->role === 'administrator') return true;
                return false;
            }
        };
    }

    private function mockAdminUser(): object
    {
        return $this->mockUser('administrator', 1);
    }

    private function ensureUser(int $id, string $role): User
    {
        $user = User::where('username', 'unit-test-user-' . $id)->find();
        if (!$user) {
            $user = new User();
            $user->username = 'unit-test-user-' . $id;
            $user->role = $role;
            $user->status = 'active';
            $user->setPassword('password123');
            $user->save();
        }
        return $user;
    }

    private function cleanUp(): void
    {
        $violationIds = Violation::where('notes', 'unit-test-violation')->column('id');
        if (!empty($violationIds)) {
            ViolationAppeal::whereIn('violation_id', $violationIds)->delete();
        }
        Violation::where('notes', 'unit-test-violation')->delete();
    }
}
