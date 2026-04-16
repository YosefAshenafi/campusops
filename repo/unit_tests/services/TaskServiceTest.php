<?php

declare(strict_types=1);

namespace tests\services;

use app\model\ActivityGroup;
use app\model\Task;
use app\service\TaskService;
use PHPUnit\Framework\TestCase;

class TaskServiceTest extends TestCase
{
    private TaskService $service;
    private ActivityGroup $activity;
    private object $ownerUser;
    private object $adminUser;
    private object $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TaskService();
        $this->cleanUp();

        // Create a test activity
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
    // getTasks
    // ------------------------------------------------------------------

    public function testGetTasksReturnsEmptyArrayWhenNoTasksExist(): void
    {
        $result = $this->service->getTasks($this->activity->id);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetTasksReturnsCreatedTasks(): void
    {
        $this->insertTask($this->activity->id, 'Task A');
        $this->insertTask($this->activity->id, 'Task B');

        $result = $this->service->getTasks($this->activity->id);

        $this->assertCount(2, $result);
        $titles = array_column($result, 'title');
        $this->assertContains('Task A', $titles);
        $this->assertContains('Task B', $titles);
    }

    public function testGetTasksResultHasExpectedFields(): void
    {
        $this->insertTask($this->activity->id, 'Task Fields Test');

        $result = $this->service->getTasks($this->activity->id);

        $this->assertNotEmpty($result);
        $item = $result[0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('activity_id', $item);
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('status', $item);
        $this->assertArrayHasKey('description', $item);
    }

    public function testGetTasksDoesNotReturnTasksFromOtherActivities(): void
    {
        $other = new ActivityGroup();
        $other->created_by = 1;
        $other->save();
        $this->insertTask($other->id, 'Other Activity Task');

        $result = $this->service->getTasks($this->activity->id);

        $titles = array_column($result, 'title');
        $this->assertNotContains('Other Activity Task', $titles);

        Task::where('activity_id', $other->id)->delete();
        ActivityGroup::where('id', $other->id)->delete();
    }

    // ------------------------------------------------------------------
    // createTask
    // ------------------------------------------------------------------

    public function testCreateTaskSucceedsForOwner(): void
    {
        $result = $this->service->createTask($this->activity->id, [
            'title'       => 'unit-test-task-create',
            'description' => 'test description',
        ], $this->ownerUser);

        $this->assertEquals('unit-test-task-create', $result['title']);
        $this->assertEquals(TaskService::STATUS_PENDING, $result['status']);
        $this->assertEquals($this->activity->id, $result['activity_id']);
    }

    public function testCreateTaskSucceedsForAdmin(): void
    {
        $result = $this->service->createTask($this->activity->id, [
            'title' => 'unit-test-task-admin-create',
        ], $this->adminUser);

        $this->assertEquals('unit-test-task-admin-create', $result['title']);
    }

    public function testCreateTaskThrows400WhenTitleMissing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->createTask($this->activity->id, [], $this->ownerUser);
    }

    public function testCreateTaskThrows403ForNonOwner(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->createTask($this->activity->id, [
            'title' => 'unauthorized task',
        ], $this->otherUser);
    }

    public function testCreateTaskSetsOptionalFields(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $result = $this->service->createTask($this->activity->id, [
            'title'    => 'unit-test-task-optional',
            'due_date' => $tomorrow,
        ], $this->ownerUser);

        $this->assertEquals($tomorrow, $result['due_date']);
    }

    // ------------------------------------------------------------------
    // updateTask
    // ------------------------------------------------------------------

    public function testUpdateTaskChangesTitle(): void
    {
        $task = $this->insertTask($this->activity->id, 'Before Update');

        $result = $this->service->updateTask($task->id, ['title' => 'After Update'], $this->ownerUser);

        $this->assertEquals('After Update', $result['title']);
    }

    public function testUpdateTaskThrows404WhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->updateTask(999999, ['title' => 'ghost'], $this->adminUser);
    }

    public function testUpdateTaskThrows403ForNonOwner(): void
    {
        $task = $this->insertTask($this->activity->id, 'Protected Task');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->updateTask($task->id, ['title' => 'hacked'], $this->otherUser);
    }

    // ------------------------------------------------------------------
    // updateStatus
    // ------------------------------------------------------------------

    public function testUpdateStatusChangesStatus(): void
    {
        $task = $this->insertTask($this->activity->id, 'Status Test');

        $result = $this->service->updateStatus($task->id, TaskService::STATUS_IN_PROGRESS, $this->ownerUser);

        $this->assertEquals(TaskService::STATUS_IN_PROGRESS, $result['status']);
    }

    public function testUpdateStatusThrows400ForInvalidStatus(): void
    {
        $task = $this->insertTask($this->activity->id, 'Bad Status Task');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->updateStatus($task->id, 'invalid_status', $this->ownerUser);
    }

    public function testUpdateStatusThrows404WhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->updateStatus(999999, TaskService::STATUS_COMPLETED, $this->adminUser);
    }

    public function testUpdateStatusThrows403ForNonOwner(): void
    {
        $task = $this->insertTask($this->activity->id, 'Status Forbidden');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->updateStatus($task->id, TaskService::STATUS_COMPLETED, $this->otherUser);
    }

    // ------------------------------------------------------------------
    // deleteTask
    // ------------------------------------------------------------------

    public function testDeleteTaskRemovesRecord(): void
    {
        $task = $this->insertTask($this->activity->id, 'Delete Me');

        $this->service->deleteTask($task->id, $this->ownerUser);

        $this->assertNull(Task::find($task->id));
    }

    public function testDeleteTaskThrows404WhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->deleteTask(999999, $this->adminUser);
    }

    public function testDeleteTaskThrows403ForNonOwner(): void
    {
        $task = $this->insertTask($this->activity->id, 'Protected Delete');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->deleteTask($task->id, $this->otherUser);
    }

    public function testAdminCanDeleteAnyTask(): void
    {
        $task = $this->insertTask($this->activity->id, 'Admin Delete');

        $this->service->deleteTask($task->id, $this->adminUser);

        $this->assertNull(Task::find($task->id));
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

    private function insertTask(int $activityId, string $title): Task
    {
        $task = new Task();
        $task->activity_id = $activityId;
        $task->title       = $title;
        $task->status      = TaskService::STATUS_PENDING;
        $task->save();
        return $task;
    }

    private function cleanUp(): void
    {
        if (isset($this->activity)) {
            Task::where('activity_id', $this->activity->id)->delete();
            ActivityGroup::where('id', $this->activity->id)->delete();
        }
    }
}
