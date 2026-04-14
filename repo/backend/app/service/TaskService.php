<?php

namespace app\service;

use app\model\Task;
use app\model\User;
use app\model\ActivityGroup;

class TaskService
{
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';

    /**
     * Get tasks for an activity.
     */
    public function getTasks(int $activityId): array
    {
        $tasks = Task::where('activity_id', $activityId)->order('id', 'desc')->select();
        return array_map(fn($t) => $this->formatTask($t), $tasks);
    }

    /**
     * Create a task.
     */
    public function createTask(int $activityId, array $data, $currentUser): array
    {
        $this->assertActivityAccess($activityId, $currentUser);

        if (empty($data['title'])) {
            throw new \Exception('Title is required', 400);
        }

        $task = new Task();
        $task->activity_id = $activityId;
        $task->title = $data['title'];
        $task->description = $data['description'] ?? '';
        $task->assigned_to = $data['assigned_to'] ?? null;
        $task->status = self::STATUS_PENDING;
        $task->due_date = $data['due_date'] ?? null;
        $task->save();

        return $this->formatTask($task);
    }

    /**
     * Update a task.
     */
    public function updateTask(int $id, array $data, $currentUser): array
    {
        $task = Task::find($id);
        if (!$task) {
            throw new \Exception('Task not found', 404);
        }

        $this->assertActivityAccess($task->activity_id, $currentUser);

        if (isset($data['title'])) $task->title = $data['title'];
        if (isset($data['description'])) $task->description = $data['description'];
        if (isset($data['assigned_to'])) $task->assigned_to = $data['assigned_to'];
        if (isset($data['due_date'])) $task->due_date = $data['due_date'];

        $task->save();
        return $this->formatTask($task);
    }

    /**
     * Update task status.
     */
    public function updateStatus(int $id, string $status, $currentUser): array
    {
        $task = Task::find($id);
        if (!$task) {
            throw new \Exception('Task not found', 404);
        }

        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED])) {
            throw new \Exception('Invalid status', 400);
        }

        $this->assertActivityAccess($task->activity_id, $currentUser);

        $task->status = $status;
        $task->save();

        return $this->formatTask($task);
    }

    /**
     * Delete a task.
     */
    public function deleteTask(int $id, $currentUser): void
    {
        $task = Task::find($id);
        if (!$task) {
            throw new \Exception('Task not found', 404);
        }

        $this->assertActivityAccess($task->activity_id, $currentUser);

        $task->delete();
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
     * Format task for API response.
     */
    protected function formatTask(Task $task): array
    {
        $assignee = $task->assigned_to ? User::find($task->assigned_to) : null;
        return [
            'id' => $task->id,
            'activity_id' => $task->activity_id,
            'title' => $task->title,
            'description' => $task->description,
            'assigned_to' => $task->assigned_to,
            'assignee_name' => $assignee ? $assignee->username : null,
            'status' => $task->status,
            'due_date' => $task->due_date,
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
        ];
    }
}