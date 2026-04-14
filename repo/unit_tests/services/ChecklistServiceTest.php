<?php

declare(strict_types=1);

namespace tests\services;

use app\model\Checklist;
use app\model\ChecklistItem;
use app\model\ActivityGroup;
use app\service\ChecklistService;
use PHPUnit\Framework\TestCase;

class ChecklistServiceTest extends TestCase
{
    private ChecklistService $service;
    private static int $activityId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChecklistService();

        // Ensure a test activity exists
        $group = ActivityGroup::where('created_by', 999)->find();
        if (!$group) {
            $group = new ActivityGroup();
            $group->created_by = 999;
            $group->save();
        }
        self::$activityId = $group->id;
    }

    protected function tearDown(): void
    {
        // Clean up test checklists
        $checklists = Checklist::where('activity_id', self::$activityId)->select();
        foreach ($checklists as $cl) {
            ChecklistItem::where('checklist_id', $cl->id)->delete();
        }
        Checklist::where('activity_id', self::$activityId)->delete();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // completeItem object-level access control
    // ------------------------------------------------------------------

    public function testCompleteItemSucceedsForActivityOwner(): void
    {
        $cl = $this->createChecklist();
        $item = $this->createItem($cl->id);
        $owner = $this->mockUser('regular_user', 999);

        $result = $this->service->completeItem($cl->id, $item->id, $owner);
        $this->assertTrue((bool) $result['completed']);
    }

    public function testCompleteItemSucceedsForAdmin(): void
    {
        $cl = $this->createChecklist();
        $item = $this->createItem($cl->id);
        $admin = $this->mockUser('administrator', 1);

        $result = $this->service->completeItem($cl->id, $item->id, $admin);
        $this->assertTrue((bool) $result['completed']);
    }

    public function testCompleteItemThrows403ForNonOwner(): void
    {
        $cl = $this->createChecklist();
        $item = $this->createItem($cl->id);
        $other = $this->mockUser('regular_user', 888);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $this->service->completeItem($cl->id, $item->id, $other);
    }

    public function testCompleteItemTogglesCompletion(): void
    {
        $cl = $this->createChecklist();
        $item = $this->createItem($cl->id);
        $owner = $this->mockUser('regular_user', 999);

        $result1 = $this->service->completeItem($cl->id, $item->id, $owner);
        $this->assertTrue((bool) $result1['completed']);

        $result2 = $this->service->completeItem($cl->id, $item->id, $owner);
        $this->assertFalse((bool) $result2['completed']);
    }

    // ------------------------------------------------------------------
    // createChecklist object-level access control
    // ------------------------------------------------------------------

    public function testCreateChecklistSucceedsForActivityOwner(): void
    {
        $owner = $this->mockUser('regular_user', 999);
        $result = $this->service->createChecklist(self::$activityId, ['title' => 'owner-checklist'], $owner);
        $this->assertEquals('owner-checklist', $result['title']);
    }

    public function testCreateChecklistSucceedsForAdmin(): void
    {
        $admin = $this->mockUser('administrator', 1);
        $result = $this->service->createChecklist(self::$activityId, ['title' => 'admin-checklist'], $admin);
        $this->assertEquals('admin-checklist', $result['title']);
    }

    public function testCreateChecklistThrows403ForNonOwner(): void
    {
        $other = $this->mockUser('regular_user', 888);
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->createChecklist(self::$activityId, ['title' => 'unauthorized-checklist'], $other);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createChecklist(): Checklist
    {
        $cl = new Checklist();
        $cl->activity_id = self::$activityId;
        $cl->title = 'test-checklist';
        $cl->save();
        return $cl;
    }

    private function createItem(int $checklistId): ChecklistItem
    {
        $item = new ChecklistItem();
        $item->checklist_id = $checklistId;
        $item->label = 'test-item';
        $item->completed = 0;
        $item->save();
        return $item;
    }

    private function mockUser(string $role, int $id): object
    {
        return new class($role, $id) {
            public int $id;
            public string $role;
            public function __construct(string $role, int $id) {
                $this->role = $role;
                $this->id = $id;
            }
        };
    }
}
