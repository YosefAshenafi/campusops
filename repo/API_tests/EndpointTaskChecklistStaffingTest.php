<?php

declare(strict_types=1);

namespace tests\api;

use app\model\ActivityGroup;
use app\model\ActivityVersion;
use app\model\Task;
use app\model\Checklist;
use app\model\ChecklistItem;
use app\model\Staffing;

/**
 * Endpoint tests for Task, Checklist, and Staffing routes.
 *
 * Routes under test:
 *   GET    /api/v1/activities/:activity_id/tasks
 *   POST   /api/v1/activities/:activity_id/tasks
 *   PUT    /api/v1/tasks/:id
 *   PUT    /api/v1/tasks/:id/status
 *   DELETE /api/v1/tasks/:id
 *   GET    /api/v1/activities/:activity_id/checklists
 *   POST   /api/v1/activities/:activity_id/checklists
 *   PUT    /api/v1/checklists/:id
 *   DELETE /api/v1/checklists/:id
 *   POST   /api/v1/checklists/:id/items/:item_id/complete
 *   GET    /api/v1/activities/:activity_id/staffing
 *   POST   /api/v1/activities/:activity_id/staffing
 *   PUT    /api/v1/staffing/:id
 *   DELETE /api/v1/staffing/:id
 */
class EndpointTaskChecklistStaffingTest extends HttpTestCase
{
    private int $activityId   = 0;
    private int $taskId       = 0;
    private int $checklistId  = 0;
    private int $checklistItemId = 0;
    private int $staffingId   = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = '';

        // Create an ActivityGroup owned by user 1 (seeded admin).
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        // Create an ActivityVersion (the "activity") linked to the group.
        $version = new ActivityVersion();
        $version->group_id         = $group->id;
        $version->version_number   = 1;
        $version->title            = 'Test Activity for TCS';
        $version->body             = 'body text';
        $version->tags             = json_encode([]);
        $version->state            = 'published';
        $version->eligibility_tags = json_encode([]);
        $version->required_supplies = json_encode([]);
        $version->max_headcount    = 10;
        $version->published_at     = date('Y-m-d H:i:s');
        $version->signup_end       = date('Y-m-d H:i:s', strtotime('+7 days'));
        $version->save();

        $this->activityId = (int) $version->id;

        // Seed a Task for update/delete tests.
        $task = new Task();
        $task->activity_id  = $this->activityId;
        $task->title        = 'Seed Task';
        $task->description  = 'Seed description';
        $task->status       = 'pending';
        $task->due_date     = date('Y-m-d', strtotime('+3 days'));
        $task->save();
        $this->taskId = (int) $task->id;

        // Seed a Checklist for update/delete/complete-item tests.
        $checklist = new Checklist();
        $checklist->activity_id = $this->activityId;
        $checklist->title       = 'Seed Checklist';
        $checklist->save();
        $this->checklistId = (int) $checklist->id;

        // Seed a ChecklistItem for the completeItem test.
        $item = new ChecklistItem();
        $item->checklist_id = $this->checklistId;
        $item->label        = 'Seed Item';
        $item->completed    = 0;
        $item->save();
        $this->checklistItemId = (int) $item->id;

        // Seed a Staffing record for update/delete tests.
        $staffing = new Staffing();
        $staffing->activity_id     = $this->activityId;
        $staffing->role            = 'coordinator';
        $staffing->required_count  = 2;
        $staffing->assigned_users  = json_encode([]);
        $staffing->notes           = 'seed notes';
        $staffing->created_by      = 1;
        $staffing->save();
        $this->staffingId = (int) $staffing->id;
    }

    protected function tearDown(): void
    {
        // Remove all records created during tests to avoid cross-test contamination.
        Staffing::where('activity_id', $this->activityId)->delete();
        ChecklistItem::where('checklist_id', $this->checklistId)->delete();
        Checklist::where('activity_id', $this->activityId)->delete();
        Task::where('activity_id', $this->activityId)->delete();
        ActivityVersion::where('id', $this->activityId)->delete();
        // Clean up any extra activity versions that may have been created.
        ActivityVersion::where('title', 'like', '%Test Activity for TCS%')->delete();

        $this->cleanupUsersLike('tcs-test-%');

        $this->token = '';
        parent::tearDown();
    }

    // ======================================================================
    // Helpers
    // ======================================================================

    private function loginAdmin(): void
    {
        $this->loginAsRole('administrator', 'tcs-test-admin');
    }

    private function loginRegular(): void
    {
        $this->loginAsRole('regular_user', 'tcs-test-regular');
    }

    // ======================================================================
    // GET /api/v1/activities/:activity_id/tasks
    // ======================================================================

    public function testTaskIndexUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->get("/api/v1/activities/{$this->activityId}/tasks");
        $this->assertUnauthorized($resp);
    }

    public function testTaskIndexForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->get("/api/v1/activities/{$this->activityId}/tasks");
        $this->assertForbidden($resp);
    }

    public function testTaskIndexSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->get("/api/v1/activities/{$this->activityId}/tasks");
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testTaskIndexNotFoundForMissingActivity(): void
    {
        $this->loginAdmin();
        $resp = $this->get('/api/v1/activities/999999/tasks');
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // POST /api/v1/activities/:activity_id/tasks
    // ======================================================================

    public function testTaskCreateUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->post("/api/v1/activities/{$this->activityId}/tasks", [
            'title'       => 'New Task',
            'description' => 'desc',
            'due_date'    => date('Y-m-d', strtotime('+5 days')),
        ]);
        $this->assertUnauthorized($resp);
    }

    public function testTaskCreateForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->post("/api/v1/activities/{$this->activityId}/tasks", [
            'title'       => 'New Task',
            'description' => 'desc',
            'due_date'    => date('Y-m-d', strtotime('+5 days')),
        ]);
        $this->assertForbidden($resp);
    }

    public function testTaskCreateSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->post("/api/v1/activities/{$this->activityId}/tasks", [
            'title'       => 'New Task',
            'description' => 'A description',
            'due_date'    => date('Y-m-d', strtotime('+5 days')),
        ]);
        $this->assertStatus(201, $resp);
        $this->assertSuccess($resp);
    }

    public function testTaskCreateNotFoundForMissingActivity(): void
    {
        $this->loginAdmin();
        $resp = $this->post('/api/v1/activities/999999/tasks', [
            'title'    => 'Ghost Task',
            'due_date' => date('Y-m-d', strtotime('+1 day')),
        ]);
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // PUT /api/v1/tasks/:id
    // ======================================================================

    public function testTaskUpdateUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->put("/api/v1/tasks/{$this->taskId}", [
            'title' => 'Updated Title',
        ]);
        $this->assertUnauthorized($resp);
    }

    public function testTaskUpdateForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->put("/api/v1/tasks/{$this->taskId}", [
            'title' => 'Updated Title',
        ]);
        $this->assertForbidden($resp);
    }

    public function testTaskUpdateSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->put("/api/v1/tasks/{$this->taskId}", [
            'title'       => 'Updated Title',
            'description' => 'Updated description',
            'due_date'    => date('Y-m-d', strtotime('+10 days')),
        ]);
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testTaskUpdateNotFoundForMissingId(): void
    {
        $this->loginAdmin();
        $resp = $this->put('/api/v1/tasks/999999', [
            'title' => 'Ghost Update',
        ]);
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // PUT /api/v1/tasks/:id/status
    // ======================================================================

    public function testTaskUpdateStatusUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->put("/api/v1/tasks/{$this->taskId}/status", [
            'status' => 'completed',
        ]);
        $this->assertUnauthorized($resp);
    }

    public function testTaskUpdateStatusForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->put("/api/v1/tasks/{$this->taskId}/status", [
            'status' => 'completed',
        ]);
        $this->assertForbidden($resp);
    }

    public function testTaskUpdateStatusSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->put("/api/v1/tasks/{$this->taskId}/status", [
            'status' => 'completed',
        ]);
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testTaskUpdateStatusNotFoundForMissingId(): void
    {
        $this->loginAdmin();
        $resp = $this->put('/api/v1/tasks/999999/status', [
            'status' => 'completed',
        ]);
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // DELETE /api/v1/tasks/:id
    // ======================================================================

    public function testTaskDeleteUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->delete("/api/v1/tasks/{$this->taskId}");
        $this->assertUnauthorized($resp);
    }

    public function testTaskDeleteForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->delete("/api/v1/tasks/{$this->taskId}");
        $this->assertForbidden($resp);
    }

    public function testTaskDeleteSuccessForAdmin(): void
    {
        // Create a throwaway task so the seeded one stays intact for other tests.
        $task = new Task();
        $task->activity_id  = $this->activityId;
        $task->title        = 'Disposable Task';
        $task->description  = 'will be deleted';
        $task->status       = 'pending';
        $task->due_date     = date('Y-m-d', strtotime('+1 day'));
        $task->save();
        $disposableId = (int) $task->id;

        $this->loginAdmin();
        $resp = $this->delete("/api/v1/tasks/{$disposableId}");
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testTaskDeleteNotFoundForMissingId(): void
    {
        $this->loginAdmin();
        $resp = $this->delete('/api/v1/tasks/999999');
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // GET /api/v1/activities/:activity_id/checklists
    // ======================================================================

    public function testChecklistIndexUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->get("/api/v1/activities/{$this->activityId}/checklists");
        $this->assertUnauthorized($resp);
    }

    public function testChecklistIndexForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->get("/api/v1/activities/{$this->activityId}/checklists");
        $this->assertForbidden($resp);
    }

    public function testChecklistIndexSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->get("/api/v1/activities/{$this->activityId}/checklists");
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testChecklistIndexNotFoundForMissingActivity(): void
    {
        $this->loginAdmin();
        $resp = $this->get('/api/v1/activities/999999/checklists');
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // POST /api/v1/activities/:activity_id/checklists
    // ======================================================================

    public function testChecklistCreateUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->post("/api/v1/activities/{$this->activityId}/checklists", [
            'title' => 'New Checklist',
        ]);
        $this->assertUnauthorized($resp);
    }

    public function testChecklistCreateForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->post("/api/v1/activities/{$this->activityId}/checklists", [
            'title' => 'New Checklist',
        ]);
        $this->assertForbidden($resp);
    }

    public function testChecklistCreateSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->post("/api/v1/activities/{$this->activityId}/checklists", [
            'title' => 'New Checklist',
        ]);
        $this->assertStatus(201, $resp);
        $this->assertSuccess($resp);
    }

    public function testChecklistCreateNotFoundForMissingActivity(): void
    {
        $this->loginAdmin();
        $resp = $this->post('/api/v1/activities/999999/checklists', [
            'title' => 'Ghost Checklist',
        ]);
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // PUT /api/v1/checklists/:id
    // ======================================================================

    public function testChecklistUpdateUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->put("/api/v1/checklists/{$this->checklistId}", [
            'title' => 'Updated Checklist',
        ]);
        $this->assertUnauthorized($resp);
    }

    public function testChecklistUpdateForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->put("/api/v1/checklists/{$this->checklistId}", [
            'title' => 'Updated Checklist',
        ]);
        $this->assertForbidden($resp);
    }

    public function testChecklistUpdateSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->put("/api/v1/checklists/{$this->checklistId}", [
            'title' => 'Updated Checklist Title',
        ]);
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testChecklistUpdateNotFoundForMissingId(): void
    {
        $this->loginAdmin();
        $resp = $this->put('/api/v1/checklists/999999', [
            'title' => 'Ghost Checklist Update',
        ]);
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // DELETE /api/v1/checklists/:id
    // ======================================================================

    public function testChecklistDeleteUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->delete("/api/v1/checklists/{$this->checklistId}");
        $this->assertUnauthorized($resp);
    }

    public function testChecklistDeleteForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->delete("/api/v1/checklists/{$this->checklistId}");
        $this->assertForbidden($resp);
    }

    public function testChecklistDeleteSuccessForAdmin(): void
    {
        // Create a throwaway checklist so the seeded one stays intact.
        $checklist = new Checklist();
        $checklist->activity_id = $this->activityId;
        $checklist->title       = 'Disposable Checklist';
        $checklist->save();
        $disposableId = (int) $checklist->id;

        $this->loginAdmin();
        $resp = $this->delete("/api/v1/checklists/{$disposableId}");
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testChecklistDeleteNotFoundForMissingId(): void
    {
        $this->loginAdmin();
        $resp = $this->delete('/api/v1/checklists/999999');
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // POST /api/v1/checklists/:id/items/:item_id/complete
    // ======================================================================

    public function testChecklistCompleteItemUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->post(
            "/api/v1/checklists/{$this->checklistId}/items/{$this->checklistItemId}/complete"
        );
        $this->assertUnauthorized($resp);
    }

    public function testChecklistCompleteItemForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->post(
            "/api/v1/checklists/{$this->checklistId}/items/{$this->checklistItemId}/complete"
        );
        $this->assertForbidden($resp);
    }

    public function testChecklistCompleteItemSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->post(
            "/api/v1/checklists/{$this->checklistId}/items/{$this->checklistItemId}/complete"
        );
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testChecklistCompleteItemNotFoundForMissingChecklist(): void
    {
        $this->loginAdmin();
        $resp = $this->post(
            "/api/v1/checklists/999999/items/{$this->checklistItemId}/complete"
        );
        $this->assertNotFound($resp);
    }

    public function testChecklistCompleteItemNotFoundForMissingItem(): void
    {
        $this->loginAdmin();
        $resp = $this->post(
            "/api/v1/checklists/{$this->checklistId}/items/999999/complete"
        );
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // GET /api/v1/activities/:activity_id/staffing
    // ======================================================================

    public function testStaffingIndexUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->get("/api/v1/activities/{$this->activityId}/staffing");
        $this->assertUnauthorized($resp);
    }

    public function testStaffingIndexForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->get("/api/v1/activities/{$this->activityId}/staffing");
        $this->assertForbidden($resp);
    }

    public function testStaffingIndexSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->get("/api/v1/activities/{$this->activityId}/staffing");
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testStaffingIndexNotFoundForMissingActivity(): void
    {
        $this->loginAdmin();
        $resp = $this->get('/api/v1/activities/999999/staffing');
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // POST /api/v1/activities/:activity_id/staffing
    // ======================================================================

    public function testStaffingCreateUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->post("/api/v1/activities/{$this->activityId}/staffing", [
            'role'           => 'facilitator',
            'required_count' => 1,
        ]);
        $this->assertUnauthorized($resp);
    }

    public function testStaffingCreateForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->post("/api/v1/activities/{$this->activityId}/staffing", [
            'role'           => 'facilitator',
            'required_count' => 1,
        ]);
        $this->assertForbidden($resp);
    }

    public function testStaffingCreateSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->post("/api/v1/activities/{$this->activityId}/staffing", [
            'role'           => 'facilitator',
            'required_count' => 2,
            'notes'          => 'test notes',
        ]);
        $this->assertStatus(201, $resp);
        $this->assertSuccess($resp);
    }

    public function testStaffingCreateNotFoundForMissingActivity(): void
    {
        $this->loginAdmin();
        $resp = $this->post('/api/v1/activities/999999/staffing', [
            'role'           => 'ghost_role',
            'required_count' => 1,
        ]);
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // PUT /api/v1/staffing/:id
    // ======================================================================

    public function testStaffingUpdateUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->put("/api/v1/staffing/{$this->staffingId}", [
            'role'           => 'updated_role',
            'required_count' => 3,
        ]);
        $this->assertUnauthorized($resp);
    }

    public function testStaffingUpdateForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->put("/api/v1/staffing/{$this->staffingId}", [
            'role'           => 'updated_role',
            'required_count' => 3,
        ]);
        $this->assertForbidden($resp);
    }

    public function testStaffingUpdateSuccessForAdmin(): void
    {
        $this->loginAdmin();
        $resp = $this->put("/api/v1/staffing/{$this->staffingId}", [
            'role'           => 'updated_role',
            'required_count' => 3,
            'notes'          => 'updated notes',
        ]);
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testStaffingUpdateNotFoundForMissingId(): void
    {
        $this->loginAdmin();
        $resp = $this->put('/api/v1/staffing/999999', [
            'role'           => 'ghost_role',
            'required_count' => 1,
        ]);
        $this->assertNotFound($resp);
    }

    // ======================================================================
    // DELETE /api/v1/staffing/:id
    // ======================================================================

    public function testStaffingDeleteUnauthorized(): void
    {
        $this->token = '';
        $resp = $this->delete("/api/v1/staffing/{$this->staffingId}");
        $this->assertUnauthorized($resp);
    }

    public function testStaffingDeleteForbiddenForRegularUser(): void
    {
        $this->loginRegular();
        $resp = $this->delete("/api/v1/staffing/{$this->staffingId}");
        $this->assertForbidden($resp);
    }

    public function testStaffingDeleteSuccessForAdmin(): void
    {
        // Create a throwaway staffing record so the seeded one stays intact.
        $staffing = new Staffing();
        $staffing->activity_id    = $this->activityId;
        $staffing->role           = 'disposable_role';
        $staffing->required_count = 1;
        $staffing->assigned_users = json_encode([]);
        $staffing->notes          = 'will be deleted';
        $staffing->created_by     = 1;
        $staffing->save();
        $disposableId = (int) $staffing->id;

        $this->loginAdmin();
        $resp = $this->delete("/api/v1/staffing/{$disposableId}");
        $this->assertStatus(200, $resp);
        $this->assertSuccess($resp);
    }

    public function testStaffingDeleteNotFoundForMissingId(): void
    {
        $this->loginAdmin();
        $resp = $this->delete('/api/v1/staffing/999999');
        $this->assertNotFound($resp);
    }
}
