<?php

namespace app\service;

use app\model\Staffing;
use app\model\ActivityGroup;

class StaffingService
{
    public function getStaffing(int $activityId): array
    {
        $staffing = Staffing::where('activity_id', $activityId)->select();
        return array_map(fn($s) => $this->format($s), $staffing);
    }

    public function createStaffing(int $activityId, array $data, $currentUser): array
    {
        $this->assertActivityAccess($activityId, $currentUser);

        if (empty($data['role'])) {
            throw new \Exception('Role is required', 400);
        }

        $staffing = new Staffing();
        $staffing->activity_id = $activityId;
        $staffing->role = $data['role'];
        $staffing->required_count = $data['required_count'] ?? 1;
        $staffing->assigned_users = json_encode($data['assigned_users'] ?? []);
        $staffing->notes = $data['notes'] ?? '';
        $staffing->created_by = $currentUser->id;
        $staffing->save();

        return $this->format($staffing);
    }

    public function updateStaffing(int $id, array $data, $currentUser): array
    {
        $staffing = Staffing::find($id);
        if (!$staffing) {
            throw new \Exception('Staffing not found', 404);
        }

        $this->assertActivityAccess($staffing->activity_id, $currentUser);

        if (isset($data['role'])) $staffing->role = $data['role'];
        if (isset($data['required_count'])) $staffing->required_count = $data['required_count'];
        if (isset($data['assigned_users'])) $staffing->assigned_users = json_encode($data['assigned_users']);
        if (isset($data['notes'])) $staffing->notes = $data['notes'];

        $staffing->save();
        return $this->format($staffing);
    }

    public function deleteStaffing(int $id, $currentUser): void
    {
        $staffing = Staffing::find($id);
        if (!$staffing) {
            throw new \Exception('Staffing not found', 404);
        }

        $this->assertActivityAccess($staffing->activity_id, $currentUser);

        $staffing->delete();
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

    protected function format(Staffing $s): array
    {
        return [
            'id' => $s->id,
            'activity_id' => $s->activity_id,
            'role' => $s->role,
            'required_count' => $s->required_count,
            'assigned_users' => json_decode($s->assigned_users, true) ?: [],
            'notes' => $s->notes,
            'created_at' => $s->created_at,
        ];
    }
}