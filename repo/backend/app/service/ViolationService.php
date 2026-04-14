<?php

namespace app\service;

use app\model\ViolationRule;
use app\model\Violation;
use app\model\ViolationEvidence;
use app\model\ViolationAppeal;
use app\model\UserGroup;
use app\model\UserGroupMember;
use app\model\User;
use app\model\Notification;
use app\model\FileUpload;

class ViolationService
{
    protected AuditService $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_RESOLVED = 'resolved';

    const ALERT_THRESHOLD_25 = 25;
    const ALERT_THRESHOLD_50 = 50;

    /**
     * List violation rules.
     */
    public function listRules(): array
    {
        $rules = ViolationRule::order('id', 'desc')->select();
        return array_map(fn($r) => $this->formatRule($r), $rules->all());
    }

    /**
     * Get rule by ID.
     */
    public function getRule(int $id): array
    {
        $rule = ViolationRule::find($id);
        if (!$rule) {
            throw new \Exception('Rule not found', 404);
        }
        return $this->formatRule($rule);
    }

    /**
     * Create violation rule.
     */
    public function createRule(array $data, $currentUser): array
    {
        if (empty($data['name']) || empty($data['points'])) {
            throw new \Exception('Name and points are required', 400);
        }

        $rule = new ViolationRule();
        $rule->name = $data['name'];
        $rule->description = $data['description'] ?? '';
        $rule->points = $data['points'];
        $rule->category = $data['category'] ?? 'general';
        $rule->created_by = $currentUser->id;
        $rule->save();

        return $this->formatRule($rule);
    }

    /**
     * Update violation rule.
     */
    public function updateRule(int $id, array $data, $currentUser): array
    {
        $rule = ViolationRule::find($id);
        if (!$rule) {
            throw new \Exception('Rule not found', 404);
        }

        if (isset($data['name'])) $rule->name = $data['name'];
        if (isset($data['description'])) $rule->description = $data['description'];
        if (isset($data['points'])) $rule->points = $data['points'];
        if (isset($data['category'])) $rule->category = $data['category'];

        $rule->save();
        return $this->formatRule($rule);
    }

    /**
     * Delete violation rule.
     */
    public function deleteRule(int $id, $currentUser): void
    {
        $rule = ViolationRule::find($id);
        if (!$rule) {
            throw new \Exception('Rule not found', 404);
        }
        $rule->delete();
    }

    /**
     * List violations.
     * Enforces object-level authorization: regular_user can only see their own violations.
     */
    public function listViolations(int $page = 1, int $limit = 20, string $userId = '', string $groupId = '', int $currentUserId = 0, string $currentRole = ''): array
    {
        // Object-level authorization: regular_user may only view their own violations
        if ($currentRole === 'regular_user' && $currentUserId > 0) {
            $userId = (string) $currentUserId;
        }

        $query = Violation::order('id', 'desc');

        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        // Filter by group: resolve group membership to user IDs
        if (!empty($groupId)) {
            $memberIds = UserGroupMember::where('group_id', (int) $groupId)->column('user_id');
            if (!empty($memberIds)) {
                $query->whereIn('user_id', $memberIds);
            } else {
                // No members in group — return empty result immediately
                return ['list' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];
            }
        }

        $total = $query->count();
        $violations = $query->page($page, $limit)->select();

        $list = [];
        foreach ($violations as $v) {
            $list[] = $this->formatViolation($v);
        }

        return ['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    /**
     * Get violation by ID.
     * Enforces object-level authorization: regular_user can only fetch their own violations.
     */
    public function getViolation(int $id, int $currentUserId = 0, string $currentRole = ''): array
    {
        $violation = Violation::find($id);
        if (!$violation) {
            throw new \Exception('Violation not found', 404);
        }

        if ($currentRole === 'regular_user' && $currentUserId > 0 && $violation->user_id != $currentUserId) {
            throw new \Exception('Access denied', 403);
        }

        return $this->formatViolation($violation);
    }

    /**
     * Create violation with points and alerts.
     */
    public function createViolation(array $data, $currentUser): array
    {
        if (empty($data['user_id']) || empty($data['rule_id'])) {
            throw new \Exception('User ID and rule ID are required', 400);
        }

        $rule = ViolationRule::find($data['rule_id']);
        if (!$rule) {
            throw new \Exception('Rule not found', 404);
        }

        $violation = new Violation();
        $violation->user_id = $data['user_id'];
        $violation->rule_id = $data['rule_id'];
        $violation->points = $rule->points;
        $violation->notes = $data['notes'] ?? '';
        $violation->status = self::STATUS_PENDING;
        $violation->created_by = $currentUser->id;
        $violation->save();

        $user = User::find($data['user_id']);
        $this->updateUserPoints($user);
        $this->checkAlerts($user);
        $this->checkGroupAlerts($user);

        $this->auditService->log($currentUser->id, 'violation', $violation->id, 'create', '', self::STATUS_PENDING, ['user_id' => $data['user_id'], 'rule_id' => $data['rule_id'], 'points' => $rule->points]);
        \think\facade\Log::info("Violation {$violation->id} created for user {$data['user_id']} (rule: {$data['rule_id']}, points: {$rule->points}) by user {$currentUser->id}");

        if (!empty($data['evidence_file_ids'])) {
            foreach ($data['evidence_file_ids'] as $fileId) {
                $fileUpload = FileUpload::find($fileId);
                if (!$fileUpload) {
                    throw new \Exception("Evidence file ID {$fileId} not found or not uploaded", 400);
                }
                $evidence = new ViolationEvidence();
                $evidence->violation_id = $violation->id;
                $evidence->filename = $fileUpload->filename;
                $evidence->sha256 = $fileUpload->sha256;
                $evidence->file_path = $fileUpload->file_path;
                $evidence->save();
            }
        }

        return $this->formatViolation($violation);
    }

    /**
     * Get user violations.
     */
    public function getUserViolations(int $userId): array
    {
        $violations = Violation::where('user_id', $userId)
            ->where('status', '<>', self::STATUS_REJECTED)
            ->order('id', 'desc')
            ->select();

        $list = [];
        $totalPoints = 0;
        foreach ($violations as $v) {
            $list[] = $this->formatViolation($v);
            $totalPoints += $v->points;
        }

        return ['violations' => $list, 'total_points' => $totalPoints];
    }

    /**
     * Get group violations (aggregated).
     */
    public function getGroupViolations(int $groupId): array
    {
        $memberIds = UserGroupMember::where('group_id', $groupId)->column('user_id');
        $violations = Violation::whereIn('user_id', $memberIds)
            ->where('status', '<>', self::STATUS_REJECTED)
            ->select();

        $totalPoints = 0;
        foreach ($violations as $v) {
            $totalPoints += $v->points;
        }

        return ['total_points' => $totalPoints, 'member_count' => count($memberIds)];
    }

    /**
     * Submit appeal.
     */
    public function submitAppeal(int $violationId, array $data, $currentUser): void
    {
        $violation = Violation::find($violationId);
        if (!$violation) {
            throw new \Exception('Violation not found', 404);
        }

        // Object-level authorization: only the violation subject or privileged roles may appeal
        if (!in_array($currentUser->role, ['administrator', 'reviewer'])) {
            if ($violation->user_id != $currentUser->id) {
                throw new \Exception('Access denied: you can only appeal your own violations', 403);
            }
        }

        $appeal = new ViolationAppeal();
        $appeal->violation_id = $violationId;
        $appeal->appellant_notes = $data['notes'] ?? '';
        $appeal->save();

        $violation->status = self::STATUS_UNDER_REVIEW;
        $violation->save();

        $this->auditService->log($currentUser->id, 'violation', $violationId, 'appeal', self::STATUS_PENDING, self::STATUS_UNDER_REVIEW);
    }

    /**
     * Review appeal.
     */
    public function reviewAppeal(int $violationId, array $data, $currentUser): void
    {
        $violation = Violation::find($violationId);
        if (!$violation) {
            throw new \Exception('Violation not found', 404);
        }

        if (!$currentUser->hasPermission('violations.review')) {
            throw new \Exception('Insufficient permissions', 403);
        }

        $notes = $data['notes'] ?? '';
        if (empty(trim($notes))) {
            throw new \Exception('Decision notes are required', 400);
        }

        $appeal = ViolationAppeal::where('violation_id', $violationId)->find();
        if ($appeal) {
            $appeal->reviewer_id = $currentUser->id;
            $appeal->decision = $data['decision'] ?? '';
            $appeal->reviewer_notes = $notes;
            $appeal->save();
        }
    }

    /**
     * Final decision.
     */
    public function finalDecision(int $violationId, array $data, $currentUser): void
    {
        $violation = Violation::find($violationId);
        if (!$violation) {
            throw new \Exception('Violation not found', 404);
        }

        if (!$currentUser->hasPermission('violations.review')) {
            throw new \Exception('Insufficient permissions', 403);
        }

        $notes = $data['notes'] ?? '';
        if (empty(trim($notes))) {
            throw new \Exception('Decision notes are required', 400);
        }

        $appeal = ViolationAppeal::where('violation_id', $violationId)->find();
        if ($appeal) {
            $appeal->final_notes = $notes;
            $appeal->save();
        }

        $newStatus = $data['uphold'] ? self::STATUS_APPROVED : self::STATUS_REJECTED;
        $violation->status = $newStatus;
        $violation->save();

        $this->auditService->log($currentUser->id, 'violation', $violationId, 'final_decision', self::STATUS_UNDER_REVIEW, $newStatus, ['uphold' => $data['uphold']]);

        if ($newStatus === self::STATUS_REJECTED) {
            $user = User::find($violation->user_id);
            $this->updateUserPoints($user);
            $this->checkGroupAlerts($user);
        }
    }

    /**
     * Update user total points.
     */
    protected function updateUserPoints(User $user): void
    {
        $total = Violation::where('user_id', $user->id)
            ->where('status', '<>', self::STATUS_REJECTED)
            ->sum('points');

        $user->violation_points = $total;
        $user->save();
    }

    /**
     * Check alert thresholds and create notifications.
     */
    protected function checkAlerts(User $user): void
    {
        $total = $user->violation_points ?? 0;

        if ($total >= self::ALERT_THRESHOLD_50) {
            $this->createAlert($user, 50);
        } elseif ($total >= self::ALERT_THRESHOLD_25) {
            $this->createAlert($user, 25);
        }
    }

    /**
     * Check group-level alert thresholds and notify admins.
     */
    protected function checkGroupAlerts(User $user): void
    {
        $groupIds = UserGroupMember::where('user_id', $user->id)->column('group_id');

        foreach ($groupIds as $groupId) {
            $memberIds = UserGroupMember::where('group_id', $groupId)->column('user_id');
            $groupTotal = Violation::whereIn('user_id', $memberIds)
                ->where('status', '<>', self::STATUS_REJECTED)
                ->sum('points');

            $threshold = null;
            if ($groupTotal >= self::ALERT_THRESHOLD_50) {
                $threshold = 50;
            } elseif ($groupTotal >= self::ALERT_THRESHOLD_25) {
                $threshold = 25;
            }

            if ($threshold !== null) {
                $body = "Group {$groupId} has reached {$threshold} violation points (total: {$groupTotal}).";

                $existing = Notification::where('type', 'group_violation_alert')
                    ->where('body', $body)
                    ->find();
                if ($existing) {
                    continue;
                }

                $adminIds = User::where('role', 'administrator')->column('id');
                foreach ($adminIds as $adminId) {
                    $notification = new Notification();
                    $notification->user_id = $adminId;
                    $notification->type = 'group_violation_alert';
                    $notification->title = "Group Violation Alert: {$threshold} Points";
                    $notification->body = $body;
                    $notification->save();
                }
            }
        }
    }

    /**
     * Create alert notification.
     */
    protected function createAlert(User $user, int $threshold): void
    {
        $notification = new Notification();
        $notification->user_id = $user->id;
        $notification->type = 'violation_alert';
        $notification->title = "Violation Alert: {$threshold} Points";
        $notification->body = "User {$user->username} has reached {$threshold} violation points.";
        $notification->save();
    }

    /**
     * Format rule for API response.
     */
    protected function formatRule(ViolationRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'points' => $rule->points,
            'category' => $rule->category,
            'created_at' => $rule->created_at,
        ];
    }

    /**
     * Format violation for API response.
     */
    protected function formatViolation(Violation $violation): array
    {
        $rule = ViolationRule::find($violation->rule_id);
        $user = User::find($violation->user_id);
        $creator = User::find($violation->created_by);

        return [
            'id' => $violation->id,
            'user_id' => $violation->user_id,
            'username' => $user ? $user->username : 'Unknown',
            'rule_id' => $violation->rule_id,
            'rule_name' => $rule ? $rule->name : 'Unknown',
            'points' => $violation->points,
            'notes' => $violation->notes,
            'status' => $violation->status,
            'created_by' => $violation->created_by,
            'creator_name' => $creator ? $creator->username : 'Unknown',
            'created_at' => $violation->created_at,
        ];
    }
}