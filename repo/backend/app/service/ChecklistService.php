<?php

namespace app\service;

use app\model\Checklist;
use app\model\ChecklistItem;
use app\model\ActivityGroup;

class ChecklistService
{
    /**
     * Get checklists for an activity.
     */
    public function getChecklists(int $activityId): array
    {
        $checklists = Checklist::where('activity_id', $activityId)->order('id', 'desc')->select();
        
        $result = [];
        foreach ($checklists as $cl) {
            $items = ChecklistItem::where('checklist_id', $cl->id)->select();
            $result[] = [
                'id' => $cl->id,
                'activity_id' => $cl->activity_id,
                'title' => $cl->title,
                'items' => array_map(fn($i) => $this->formatItem($i), $items),
                'created_at' => $cl->created_at,
            ];
        }
        
        return $result;
    }

    /**
     * Create a checklist.
     */
    public function createChecklist(int $activityId, array $data, $currentUser): array
    {
        if (empty($data['title'])) {
            throw new \Exception('Title is required', 400);
        }

        $checklist = new Checklist();
        $checklist->activity_id = $activityId;
        $checklist->title = $data['title'];
        $checklist->save();

        if (!empty($data['items'])) {
            foreach ($data['items'] as $label) {
                $item = new ChecklistItem();
                $item->checklist_id = $checklist->id;
                $item->label = $label;
                $item->completed = false;
                $item->save();
            }
        }

        return $this->formatChecklist($checklist);
    }

    /**
     * Update a checklist.
     */
    public function updateChecklist(int $id, array $data, $currentUser): array
    {
        $checklist = Checklist::find($id);
        if (!$checklist) {
            throw new \Exception('Checklist not found', 404);
        }

        $this->assertActivityAccess($checklist->activity_id, $currentUser);

        if (isset($data['title'])) {
            $checklist->title = $data['title'];
            $checklist->save();
        }

        return $this->formatChecklist($checklist);
    }

    /**
     * Delete a checklist.
     */
    public function deleteChecklist(int $id, $currentUser): void
    {
        $checklist = Checklist::find($id);
        if (!$checklist) {
            throw new \Exception('Checklist not found', 404);
        }

        $this->assertActivityAccess($checklist->activity_id, $currentUser);

        ChecklistItem::where('checklist_id', $id)->delete();
        $checklist->delete();
    }

    /**
     * Assert the current user has access to mutate records in an activity.
     */
    protected function assertActivityAccess(int $activityId, $currentUser): void
    {
        if ($currentUser->role === 'administrator') {
            return;
        }
        $activity = ActivityGroup::find($activityId);
        if ($activity && $activity->created_by !== $currentUser->id) {
            throw new \Exception('Access denied', 403);
        }
    }

    /**
     * Complete/uncomplete an item.
     */
    public function completeItem(int $checklistId, int $itemId, $currentUser): array
    {
        $item = ChecklistItem::find($itemId);
        if (!$item || $item->checklist_id != $checklistId) {
            throw new \Exception('Item not found', 404);
        }

        $item->completed = !$item->completed;
        if ($item->completed) {
            $item->completed_by = $currentUser->id;
            $item->completed_at = date('Y-m-d H:i:s');
        }
        $item->save();

        return $this->formatItem($item);
    }

    /**
     * Format checklist.
     */
    protected function formatChecklist(Checklist $cl): array
    {
        $items = ChecklistItem::where('checklist_id', $cl->id)->select();
        return [
            'id' => $cl->id,
            'activity_id' => $cl->activity_id,
            'title' => $cl->title,
            'items' => array_map(fn($i) => $this->formatItem($i), $items),
            'created_at' => $cl->created_at,
        ];
    }

    /**
     * Format item.
     */
    protected function formatItem(ChecklistItem $item): array
    {
        return [
            'id' => $item->id,
            'label' => $item->label,
            'completed' => $item->completed,
            'completed_by' => $item->completed_by,
            'completed_at' => $item->completed_at,
        ];
    }
}