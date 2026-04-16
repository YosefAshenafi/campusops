<?php

declare(strict_types=1);

namespace tests\services;

use app\model\ActivityGroup;
use app\model\Staffing;
use app\service\StaffingService;
use PHPUnit\Framework\TestCase;

class StaffingServiceTest extends TestCase
{
    private StaffingService $service;
    private ActivityGroup $activity;
    private object $ownerUser;
    private object $adminUser;
    private object $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StaffingService();
        $this->cleanUp();

        $this->activity = new ActivityGroup();
        $this->activity->created_by = 42;
        $this->activity->save();

        $this->ownerUser = $this->mockUser(42, 'team_lead');
        $this->adminUser = $this->mockUser(1,  'administrator');
        $this->otherUser = $this->mockUser(99, 'team_lead');
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // getStaffing
    // ------------------------------------------------------------------

    public function testGetStaffingReturnsEmptyArrayWhenNoneExist(): void
    {
        $result = $this->service->getStaffing($this->activity->id);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetStaffingReturnsCreatedRecords(): void
    {
        $this->insertStaffing($this->activity->id, 'Volunteer');
        $this->insertStaffing($this->activity->id, 'Coordinator');

        $result = $this->service->getStaffing($this->activity->id);

        $this->assertCount(2, $result);
        $roles = array_column($result, 'role');
        $this->assertContains('Volunteer', $roles);
        $this->assertContains('Coordinator', $roles);
    }

    public function testGetStaffingResultHasExpectedFields(): void
    {
        $this->insertStaffing($this->activity->id, 'Driver');

        $result = $this->service->getStaffing($this->activity->id);

        $this->assertNotEmpty($result);
        $item = $result[0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('activity_id', $item);
        $this->assertArrayHasKey('role', $item);
        $this->assertArrayHasKey('required_count', $item);
        $this->assertArrayHasKey('assigned_users', $item);
        $this->assertArrayHasKey('notes', $item);
    }

    public function testGetStaffingDoesNotReturnOtherActivitiesRecords(): void
    {
        $other = new ActivityGroup();
        $other->created_by = 1;
        $other->save();
        $this->insertStaffing($other->id, 'OtherRole');

        $result = $this->service->getStaffing($this->activity->id);

        $roles = array_column($result, 'role');
        $this->assertNotContains('OtherRole', $roles);

        Staffing::where('activity_id', $other->id)->delete();
        ActivityGroup::where('id', $other->id)->delete();
    }

    // ------------------------------------------------------------------
    // createStaffing
    // ------------------------------------------------------------------

    public function testCreateStaffingSucceedsForOwner(): void
    {
        $result = $this->service->createStaffing($this->activity->id, [
            'role'           => 'unit-test-role',
            'required_count' => 3,
            'notes'          => 'test notes',
        ], $this->ownerUser);

        $this->assertEquals('unit-test-role', $result['role']);
        $this->assertEquals(3, $result['required_count']);
        $this->assertEquals($this->activity->id, $result['activity_id']);
    }

    public function testCreateStaffingSucceedsForAdmin(): void
    {
        $result = $this->service->createStaffing($this->activity->id, [
            'role' => 'unit-test-admin-role',
        ], $this->adminUser);

        $this->assertEquals('unit-test-admin-role', $result['role']);
    }

    public function testCreateStaffingThrows400WhenRoleMissing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->createStaffing($this->activity->id, [], $this->ownerUser);
    }

    public function testCreateStaffingThrows403ForNonOwner(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->createStaffing($this->activity->id, [
            'role' => 'unauthorized',
        ], $this->otherUser);
    }

    public function testCreateStaffingDefaultsRequiredCountToOne(): void
    {
        $result = $this->service->createStaffing($this->activity->id, [
            'role' => 'unit-test-default-count',
        ], $this->ownerUser);

        $this->assertEquals(1, $result['required_count']);
    }

    public function testCreateStaffingStoresAssignedUsers(): void
    {
        $result = $this->service->createStaffing($this->activity->id, [
            'role'           => 'unit-test-assigned',
            'assigned_users' => [1, 2, 3],
        ], $this->ownerUser);

        $this->assertEquals([1, 2, 3], $result['assigned_users']);
    }

    // ------------------------------------------------------------------
    // updateStaffing
    // ------------------------------------------------------------------

    public function testUpdateStaffingChangesRole(): void
    {
        $staffing = $this->insertStaffing($this->activity->id, 'Old Role');

        $result = $this->service->updateStaffing($staffing->id, ['role' => 'New Role'], $this->ownerUser);

        $this->assertEquals('New Role', $result['role']);
    }

    public function testUpdateStaffingChangesRequiredCount(): void
    {
        $staffing = $this->insertStaffing($this->activity->id, 'Count Role', 1);

        $result = $this->service->updateStaffing($staffing->id, ['required_count' => 5], $this->ownerUser);

        $this->assertEquals(5, $result['required_count']);
    }

    public function testUpdateStaffingThrows404WhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->updateStaffing(999999, ['role' => 'ghost'], $this->adminUser);
    }

    public function testUpdateStaffingThrows403ForNonOwner(): void
    {
        $staffing = $this->insertStaffing($this->activity->id, 'Protected Staffing');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->updateStaffing($staffing->id, ['role' => 'hacked'], $this->otherUser);
    }

    public function testAdminCanUpdateAnyStaffing(): void
    {
        $staffing = $this->insertStaffing($this->activity->id, 'Admin Edit Me');

        $result = $this->service->updateStaffing($staffing->id, ['role' => 'Admin Updated'], $this->adminUser);

        $this->assertEquals('Admin Updated', $result['role']);
    }

    // ------------------------------------------------------------------
    // deleteStaffing
    // ------------------------------------------------------------------

    public function testDeleteStaffingRemovesRecord(): void
    {
        $staffing = $this->insertStaffing($this->activity->id, 'Delete Me');

        $this->service->deleteStaffing($staffing->id, $this->ownerUser);

        $this->assertNull(Staffing::find($staffing->id));
    }

    public function testDeleteStaffingThrows404WhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->deleteStaffing(999999, $this->adminUser);
    }

    public function testDeleteStaffingThrows403ForNonOwner(): void
    {
        $staffing = $this->insertStaffing($this->activity->id, 'Guarded Staffing');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->deleteStaffing($staffing->id, $this->otherUser);
    }

    public function testAdminCanDeleteAnyStaffing(): void
    {
        $staffing = $this->insertStaffing($this->activity->id, 'Admin Delete');

        $this->service->deleteStaffing($staffing->id, $this->adminUser);

        $this->assertNull(Staffing::find($staffing->id));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function mockUser(int $id, string $role): object
    {
        return new class($id, $role) {
            public int $id;
            public string $role;
            public function __construct(int $id, string $role) {
                $this->id   = $id;
                $this->role = $role;
            }
        };
    }

    private function insertStaffing(int $activityId, string $role, int $count = 2): Staffing
    {
        $s = new Staffing();
        $s->activity_id    = $activityId;
        $s->role           = $role;
        $s->required_count = $count;
        $s->assigned_users = json_encode([]);
        $s->notes          = '';
        $s->created_by     = $this->ownerUser->id;
        $s->save();
        return $s;
    }

    private function cleanUp(): void
    {
        if (isset($this->activity)) {
            Staffing::where('activity_id', $this->activity->id)->delete();
            ActivityGroup::where('id', $this->activity->id)->delete();
        }
    }
}
