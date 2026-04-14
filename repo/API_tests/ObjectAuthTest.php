<?php

declare(strict_types=1);

namespace tests\api;

use app\model\ActivityGroup;
use app\model\User;
use app\model\Task;
use app\model\Checklist;
use app\model\Staffing;
use app\service\TaskService;
use app\service\ChecklistService;
use app\service\StaffingService;
use PHPUnit\Framework\TestCase;

/**
 * Tests that verify object-level authorization in TaskService, ChecklistService,
 * and StaffingService.  Before these fixes the services had no ownership checks,
 * meaning any authenticated user could mutate another user's records.
 */
class ObjectAuthTest extends TestCase
{
    private TaskService $taskService;
    private ChecklistService $checklistService;
    private StaffingService $staffingService;

    private User $owner;
    private User $otherUser;
    private User $admin;
    private ActivityGroup $activity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taskService      = new TaskService();
        $this->checklistService = new ChecklistService();
        $this->staffingService  = new StaffingService();

        $this->cleanUp();

        $this->owner     = $this->createUser('objauth-owner',  'team_lead');
        $this->otherUser = $this->createUser('objauth-other',  'team_lead');
        $this->admin     = $this->createUser('objauth-admin',  'administrator');

        $this->activity = new ActivityGroup();
        $this->activity->created_by = $this->owner->id;
        $this->activity->save();
    }

    protected function tearDown(): void
    {
        // Clean up records created during this test
        if ($this->activity && $this->activity->id) {
            Task::where('activity_id', $this->activity->id)->delete();
            Checklist::where('activity_id', $this->activity->id)->delete();
            Staffing::where('activity_id', $this->activity->id)->delete();
            ActivityGroup::where('id', $this->activity->id)->delete();
        }
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // TaskService
    // ------------------------------------------------------------------

    public function testNonOwnerCannotUpdateTaskInAnotherUsersActivity(): void
    {
        $task = $this->createTask($this->activity->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $this->taskService->updateTask($task->id, ['title' => 'Hacked'], $this->otherUser);
    }

    public function testNonOwnerCannotDeleteTaskInAnotherUsersActivity(): void
    {
        $task = $this->createTask($this->activity->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $this->taskService->deleteTask($task->id, $this->otherUser);
    }

    public function testNonOwnerCannotUpdateTaskStatus(): void
    {
        $task = $this->createTask($this->activity->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $this->taskService->updateStatus($task->id, 'completed', $this->otherUser);
    }

    public function testOwnerCanUpdateOwnActivityTask(): void
    {
        $task = $this->createTask($this->activity->id);

        $result = $this->taskService->updateTask($task->id, ['title' => 'Updated'], $this->owner);

        $this->assertSame('Updated', $result['title']);
    }

    public function testAdminCanUpdateAnyTask(): void
    {
        $task = $this->createTask($this->activity->id);

        $result = $this->taskService->updateTask($task->id, ['title' => 'Admin Edit'], $this->admin);

        $this->assertSame('Admin Edit', $result['title']);
    }

    // ------------------------------------------------------------------
    // ChecklistService
    // ------------------------------------------------------------------

    public function testNonOwnerCannotUpdateChecklistInAnotherUsersActivity(): void
    {
        $checklist = $this->createChecklist($this->activity->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $this->checklistService->updateChecklist($checklist->id, ['title' => 'Hacked'], $this->otherUser);
    }

    public function testNonOwnerCannotDeleteChecklistInAnotherUsersActivity(): void
    {
        $checklist = $this->createChecklist($this->activity->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $this->checklistService->deleteChecklist($checklist->id, $this->otherUser);
    }

    public function testAdminCanDeleteAnyChecklist(): void
    {
        $checklist = $this->createChecklist($this->activity->id);

        // Should not throw
        $this->checklistService->deleteChecklist($checklist->id, $this->admin);

        $this->assertNull(Checklist::find($checklist->id));
    }

    // ------------------------------------------------------------------
    // StaffingService
    // ------------------------------------------------------------------

    public function testNonOwnerCannotUpdateStaffingInAnotherUsersActivity(): void
    {
        $staffing = $this->createStaffing($this->activity->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $this->staffingService->updateStaffing($staffing->id, ['role' => 'Hacker'], $this->otherUser);
    }

    public function testNonOwnerCannotDeleteStaffingInAnotherUsersActivity(): void
    {
        $staffing = $this->createStaffing($this->activity->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $this->staffingService->deleteStaffing($staffing->id, $this->otherUser);
    }

    public function testAdminCanUpdateAnyStaffing(): void
    {
        $staffing = $this->createStaffing($this->activity->id);

        $result = $this->staffingService->updateStaffing($staffing->id, ['role' => 'Manager'], $this->admin);

        $this->assertSame('Manager', $result['role']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createUser(string $username, string $role): User
    {
        $user = User::where('username', $username)->find();
        if (!$user) {
            $user = new User();
            $user->username = $username;
        }
        $user->role   = $role;
        $user->status = 'active';
        $user->setPassword('ObjAuthTest1!');
        $user->save();
        return $user;
    }

    private function createTask(int $activityId): Task
    {
        $task = new Task();
        $task->activity_id = $activityId;
        $task->title       = 'Test Task';
        $task->status      = 'pending';
        $task->save();
        return $task;
    }

    private function createChecklist(int $activityId): Checklist
    {
        $checklist = new Checklist();
        $checklist->activity_id = $activityId;
        $checklist->title       = 'Test Checklist';
        $checklist->save();
        return $checklist;
    }

    private function createStaffing(int $activityId): Staffing
    {
        $staffing = new Staffing();
        $staffing->activity_id    = $activityId;
        $staffing->role           = 'Volunteer';
        $staffing->required_count = 2;
        $staffing->assigned_users = json_encode([]);
        $staffing->notes          = '';
        $staffing->created_by     = $this->owner->id;
        $staffing->save();
        return $staffing;
    }

    private function cleanUp(): void
    {
        foreach (['objauth-owner', 'objauth-other', 'objauth-admin'] as $u) {
            User::where('username', $u)->delete();
        }
    }
}
