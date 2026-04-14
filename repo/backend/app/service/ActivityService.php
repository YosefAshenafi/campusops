<?php

namespace app\service;

use app\model\ActivityGroup;
use app\model\ActivityVersion;
use app\model\ActivitySignup;
use app\model\ActivityChangeLog;
use app\model\User;

class ActivityService
{
    protected AuditService $auditService;
    protected SearchService $searchService;

    public function __construct()
    {
        $this->auditService = new AuditService();
        $this->searchService = new SearchService();
    }

    const STATE_DRAFT = 'draft';
    const STATE_PUBLISHED = 'published';
    const STATE_IN_PROGRESS = 'in_progress';
    const STATE_COMPLETED = 'completed';
    const STATE_ARCHIVED = 'archived';

    const STATES = ['draft', 'published', 'in_progress', 'completed', 'archived'];

    /**
     * List activities with filters.
     * Returns exactly one row per group_id — the latest version for each activity group.
     */
    public function listActivities(int $page = 1, int $limit = 20, string $state = '', string $tag = '', string $keyword = ''): array
    {
        // Subquery: max version_number per group_id
        $subQuery = \think\facade\Db::table('activity_versions')
            ->field('group_id, MAX(version_number) as max_version')
            ->group('group_id')
            ->buildSql();

        $query = ActivityVersion::alias('v')
            ->join([$subQuery => 'latest'], 'v.group_id = latest.group_id AND v.version_number = latest.max_version')
            ->order('v.id', 'desc');

        if (!empty($state)) {
            $query->where('v.state', $state);
        }

        if (!empty($tag)) {
            $query->where('v.tags', 'like', "%\"{$tag}\"%");
        }

        if (!empty($keyword)) {
            $query->where('v.title', 'like', "%{$keyword}%");
        }

        $total = $query->count();
        $versions = $query->page($page, $limit)->select();

        $list = [];
        foreach ($versions as $v) {
            $group = ActivityGroup::find($v->group_id);
            $list[] = $this->formatActivity($group, $v);
        }

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Get activity by ID.
     * Returns the latest published version as the canonical public view.
     * Falls back to the latest version if nothing has been published yet.
     * Includes has_pending_draft: true when a newer draft exists beyond the published version.
     */
    public function getActivity(int $id): array
    {
        $group = ActivityGroup::find($id);
        if (!$group) {
            throw new \Exception('Activity not found', 404);
        }

        // Latest published version is the canonical public view
        $publishedVersion = ActivityVersion::where('group_id', $id)
            ->where('state', self::STATE_PUBLISHED)
            ->order('version_number', 'desc')
            ->find();

        // Fall back to latest version if nothing is published yet
        $latestVersion = ActivityVersion::where('group_id', $id)
            ->order('version_number', 'desc')
            ->find();

        $version = $publishedVersion ?? $latestVersion;

        $data = $this->formatActivity($group, $version);
        $data['has_pending_draft'] = $publishedVersion !== null
            && $latestVersion->version_number > $publishedVersion->version_number;

        return $data;
    }

    /**
     * Get all versions of an activity.
     */
    public function getVersions(int $groupId): array
    {
        $versions = ActivityVersion::where('group_id', $groupId)
            ->order('version_number', 'desc')
            ->select();

        return array_map(fn($v) => $this->formatVersion($v), $versions->all());
    }

    /**
     * Get signups for an activity.
     * Regular users only see their own signup; privileged roles see the full roster.
     */
    public function getSignups(int $groupId, int $currentUserId = 0, string $currentRole = ''): array
    {
        $query = ActivitySignup::where('group_id', $groupId)
            ->order('id', 'desc');

        // Data isolation: regular users only see their own signup
        if ($currentRole === 'regular_user' && $currentUserId > 0) {
            $query->where('user_id', $currentUserId);
        }

        $signups = $query->select();

        $result = [];
        foreach ($signups as $s) {
            $user = User::find($s->user_id);
            $result[] = [
                'id' => $s->id,
                'user_id' => $s->user_id,
                'username' => $user ? $user->username : 'Unknown',
                'status' => $s->status,
                'acknowledged_at' => $s->acknowledged_at,
                'created_at' => $s->created_at,
            ];
        }

        return $result;
    }

    /**
     * Get change log for an activity.
     */
    public function getChangeLog(int $groupId): array
    {
        $logs = ActivityChangeLog::where('group_id', $groupId)
            ->order('id', 'desc')
            ->select();

        return array_map(fn($l) => [
            'id' => $l->id,
            'from_version' => $l->from_version,
            'to_version' => $l->to_version,
            'changes' => json_decode($l->changes, true),
            'created_at' => $l->created_at,
        ], $logs->all());
    }

    /**
     * Create a new activity.
     */
    public function createActivity(array $data, $currentUser): array
    {
        $this->validateActivityData($data);

        $group = new ActivityGroup();
        $group->created_by = $currentUser->id;
        $group->save();

        $version = new ActivityVersion();
        $version->group_id = $group->id;
        $version->version_number = 1;
        $version->title = $data['title'];
        $version->body = $data['body'] ?? '';
        $version->tags = json_encode($data['tags'] ?? []);
        $version->state = self::STATE_DRAFT;
        $version->max_headcount = $data['max_headcount'] ?? 0;
        $version->signup_start = $data['signup_start'] ?? null;
        $version->signup_end = $data['signup_end'] ?? null;
        $version->eligibility_tags = json_encode($data['eligibility_tags'] ?? []);
        $version->required_supplies = json_encode($data['required_supplies'] ?? []);
        $version->save();

        $this->auditService->log($currentUser->id, 'activity', $group->id, 'create', '', 'draft', ['title' => $data['title']]);
        $this->searchService->index('activity', $group->id, $version->title, $version->body, $data['tags'] ?? []);

        return $this->formatActivity($group, $version);
    }

    /**
     * Update an activity.
     */
    public function updateActivity(int $id, array $data, $currentUser): array
    {
        $group = ActivityGroup::find($id);
        if (!$group) {
            throw new \Exception('Activity not found', 404);
        }

        $currentVersion = ActivityVersion::where('group_id', $id)
            ->order('version_number', 'desc')
            ->find();

        // Enforce immutable-history: any edit after publication creates a new version
        if (in_array($currentVersion->state, [self::STATE_PUBLISHED, self::STATE_IN_PROGRESS, self::STATE_COMPLETED, self::STATE_ARCHIVED])) {
            return $this->createNewVersion($id, $data, $currentUser);
        }

        if (isset($data['title'])) $currentVersion->title = $data['title'];
        if (isset($data['body'])) $currentVersion->body = $data['body'];
        if (isset($data['tags'])) $currentVersion->tags = json_encode($data['tags']);
        if (isset($data['max_headcount'])) $currentVersion->max_headcount = $data['max_headcount'];
        if (isset($data['signup_start'])) $currentVersion->signup_start = $data['signup_start'];
        if (isset($data['signup_end'])) $currentVersion->signup_end = $data['signup_end'];
        if (isset($data['eligibility_tags'])) $currentVersion->eligibility_tags = json_encode($data['eligibility_tags']);
        if (isset($data['required_supplies'])) $currentVersion->required_supplies = json_encode($data['required_supplies']);

        $currentVersion->save();

        $this->auditService->log($currentUser->id, 'activity', $id, 'update', '', '', ['fields' => array_keys($data)]);
        $this->searchService->index('activity', $id, $currentVersion->title, $currentVersion->body, json_decode($currentVersion->tags, true) ?: []);

        return $this->formatActivity($group, $currentVersion);
    }

    /**
     * Create new version (when editing published activity).
     */
    protected function createNewVersion(int $groupId, array $data, $currentUser): array
    {
        $currentVersion = ActivityVersion::where('group_id', $groupId)
            ->order('version_number', 'desc')
            ->find();

        $oldData = [
            'title' => $currentVersion->title,
            'body' => $currentVersion->body,
            'tags' => json_decode($currentVersion->tags, true),
            'max_headcount' => $currentVersion->max_headcount,
            'signup_start' => $currentVersion->signup_start,
            'signup_end' => $currentVersion->signup_end,
            'eligibility_tags' => json_decode($currentVersion->eligibility_tags, true),
            'required_supplies' => json_decode($currentVersion->required_supplies, true),
        ];

        $newVersion = new ActivityVersion();
        $newVersion->group_id = $groupId;
        $newVersion->version_number = $currentVersion->version_number + 1;
        $newVersion->title = $data['title'] ?? $currentVersion->title;
        $newVersion->body = $data['body'] ?? $currentVersion->body;
        $newVersion->tags = json_encode($data['tags'] ?? json_decode($currentVersion->tags, true));
        $newVersion->state = self::STATE_DRAFT;
        $newVersion->max_headcount = $data['max_headcount'] ?? $currentVersion->max_headcount;
        $newVersion->signup_start = $data['signup_start'] ?? $currentVersion->signup_start;
        $newVersion->signup_end = $data['signup_end'] ?? $currentVersion->signup_end;
        $newVersion->eligibility_tags = json_encode($data['eligibility_tags'] ?? json_decode($currentVersion->eligibility_tags, true));
        $newVersion->required_supplies = json_encode($data['required_supplies'] ?? json_decode($currentVersion->required_supplies, true));
        $newVersion->save();

        $changes = $this->diffChanges($oldData, [
            'title' => $newVersion->title,
            'body' => $newVersion->body,
            'tags' => json_decode($newVersion->tags, true),
            'max_headcount' => $newVersion->max_headcount,
            'signup_start' => $newVersion->signup_start,
            'signup_end' => $newVersion->signup_end,
            'eligibility_tags' => json_decode($newVersion->eligibility_tags, true),
            'required_supplies' => json_decode($newVersion->required_supplies, true),
        ]);

        $changelog = new ActivityChangeLog();
        $changelog->group_id = $groupId;
        $changelog->from_version = $currentVersion->version_number;
        $changelog->to_version = $newVersion->version_number;
        $changelog->changes = json_encode($changes);
        $changelog->save();

        ActivitySignup::where('group_id', $groupId)
            ->where('status', 'confirmed')
            ->update(['status' => 'pending_acknowledgement']);

        $this->searchService->index('activity', $groupId, $newVersion->title, $newVersion->body, json_decode($newVersion->tags, true) ?: []);

        return $this->formatActivity(ActivityGroup::find($groupId), $newVersion);
    }

    /**
     * Publish activity.
     */
    public function publishActivity(int $id, $currentUser): array
    {
        $version = ActivityVersion::where('group_id', $id)
            ->order('version_number', 'desc')
            ->find();

        if (!$version) {
            throw new \Exception('Activity not found', 404);
        }

        if ($version->state !== self::STATE_DRAFT) {
            throw new \Exception('Only Draft activities can be published', 400);
        }

        $version->state = self::STATE_PUBLISHED;
        $version->published_at = date('Y-m-d H:i:s');
        $version->save();

        $this->auditService->log($currentUser->id, 'activity', $id, 'state_change', self::STATE_DRAFT, self::STATE_PUBLISHED);
        $this->searchService->index('activity', $id, $version->title, $version->body, json_decode($version->tags, true) ?: []);
        \think\facade\Log::info("Activity {$id} published by user {$currentUser->id}");

        $group = ActivityGroup::find($id);
        return $this->formatActivity($group, $version);
    }

    /**
     * Start activity.
     */
    public function startActivity(int $id, $currentUser): array
    {
        $version = ActivityVersion::where('group_id', $id)
            ->order('version_number', 'desc')
            ->find();

        if ($version->state !== self::STATE_PUBLISHED) {
            throw new \Exception('Only Published activities can be started', 400);
        }

        $version->state = self::STATE_IN_PROGRESS;
        $version->started_at = date('Y-m-d H:i:s');
        $version->save();

        $this->auditService->log($currentUser->id, 'activity', $id, 'state_change', self::STATE_PUBLISHED, self::STATE_IN_PROGRESS);

        $group = ActivityGroup::find($id);
        return $this->formatActivity($group, $version);
    }

    /**
     * Complete activity.
     */
    public function completeActivity(int $id, $currentUser): array
    {
        $version = ActivityVersion::where('group_id', $id)
            ->order('version_number', 'desc')
            ->find();

        if ($version->state !== self::STATE_IN_PROGRESS) {
            throw new \Exception('Only In Progress activities can be completed', 400);
        }

        $version->state = self::STATE_COMPLETED;
        $version->completed_at = date('Y-m-d H:i:s');
        $version->save();

        $this->auditService->log($currentUser->id, 'activity', $id, 'state_change', self::STATE_IN_PROGRESS, self::STATE_COMPLETED);

        $group = ActivityGroup::find($id);
        return $this->formatActivity($group, $version);
    }

    /**
     * Archive activity.
     */
    public function archiveActivity(int $id, $currentUser): array
    {
        $version = ActivityVersion::where('group_id', $id)
            ->order('version_number', 'desc')
            ->find();

        if ($version->state !== self::STATE_COMPLETED) {
            throw new \Exception('Only Completed activities can be archived', 400);
        }

        $version->state = self::STATE_ARCHIVED;
        $version->archived_at = date('Y-m-d H:i:s');
        $version->save();

        $this->auditService->log($currentUser->id, 'activity', $id, 'state_change', self::STATE_COMPLETED, self::STATE_ARCHIVED);
        $this->searchService->remove('activity', $id);

        $group = ActivityGroup::find($id);
        return $this->formatActivity($group, $version);
    }

    /**
     * Sign up for activity.
     */
    public function signupUser(int $groupId, $currentUser): array
    {
        $version = ActivityVersion::where('group_id', $groupId)
            ->order('version_number', 'desc')
            ->find();

        if ($version->state !== self::STATE_PUBLISHED) {
            throw new \Exception('Activity is not open for signup', 400);
        }

        $now = time();
        if ($version->signup_start && strtotime($version->signup_start) > $now) {
            throw new \Exception('Signup has not started yet', 400);
        }

        if ($version->signup_end && strtotime($version->signup_end) < $now) {
            throw new \Exception('Signup has ended', 400);
        }

        $existing = ActivitySignup::where('group_id', $groupId)
            ->where('user_id', $currentUser->id)
            ->find();
        if ($existing) {
            throw new \Exception('Already signed up', 400);
        }

        // Eligibility tag enforcement
        // TODO: Replace role-to-tag proxy with a real profile tag system
        $eligibilityTags = json_decode($version->eligibility_tags, true) ?: [];
        if (!empty($eligibilityTags) && !in_array('all_students', $eligibilityTags)) {
            $userRole = $currentUser->role ?? '';
            $hasAccess = false;
            foreach ($eligibilityTags as $tag) {
                if (in_array($tag, ['cs_students', 'engineering']) && in_array($userRole, ['team_lead', 'operations_staff'])) {
                    $hasAccess = true;
                    break;
                }
                if ($tag === 'faculty' && $userRole === 'administrator') {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) {
                throw new \Exception('User does not meet eligibility requirements for this activity', 403);
            }
        }

        if ($version->max_headcount > 0) {
            $count = ActivitySignup::where('group_id', $groupId)
                ->whereIn('status', ['confirmed', 'pending_acknowledgement'])
                ->count();
            if ($count >= $version->max_headcount) {
                throw new \Exception('Activity is full', 400);
            }
        }

        $signup = new ActivitySignup();
        $signup->group_id = $groupId;
        $signup->user_id = $currentUser->id;
        $signup->status = 'confirmed';
        $signup->save();

        return [
            'id' => $signup->id,
            'user_id' => $signup->user_id,
            'status' => $signup->status,
            'created_at' => $signup->created_at,
        ];
    }

    /**
     * Cancel signup.
     */
    public function cancelSignup(int $groupId, int $signupId, $currentUser): void
    {
        $signup = ActivitySignup::find($signupId);
        if (!$signup || $signup->group_id != $groupId) {
            throw new \Exception('Signup not found', 404);
        }

        if ($signup->user_id != $currentUser->id) {
            throw new \Exception('Cannot cancel other user signups', 403);
        }

        $signup->status = 'cancelled';
        $signup->save();
    }

    /**
     * Acknowledge changes.
     */
    public function acknowledgeChanges(int $groupId, int $signupId, $currentUser): void
    {
        $signup = ActivitySignup::find($signupId);
        if (!$signup || $signup->group_id != $groupId) {
            throw new \Exception('Signup not found', 404);
        }

        if ($signup->user_id != $currentUser->id) {
            throw new \Exception('Cannot acknowledge for other users', 403);
        }

        $signup->status = 'confirmed';
        $signup->acknowledged_at = date('Y-m-d H:i:s');
        $signup->save();
    }

    /**
     * Validate activity data.
     */
    protected function validateActivityData(array $data): void
    {
        if (empty($data['title'])) {
            throw new \Exception('Title is required', 400);
        }
    }

    /**
     * Format activity for API response.
     */
    protected function formatActivity(ActivityGroup $group, ActivityVersion $version): array
    {
        return [
            'id' => $group->id,
            'created_by' => $group->created_by,
            'version_number' => $version->version_number,
            'title' => $version->title,
            'body' => $version->body,
            'tags' => json_decode($version->tags, true) ?: [],
            'state' => $version->state,
            'max_headcount' => $version->max_headcount,
            'signup_start' => $version->signup_start,
            'signup_end' => $version->signup_end,
            'eligibility_tags' => json_decode($version->eligibility_tags, true) ?: [],
            'required_supplies' => json_decode($version->required_supplies, true) ?: [],
            'published_at' => $version->published_at,
            'started_at' => $version->started_at ?? null,
            'completed_at' => $version->completed_at ?? null,
            'archived_at' => $version->archived_at ?? null,
            'created_at' => $group->created_at,
            'current_signups' => ActivitySignup::where('group_id', $group->id)
                ->whereIn('status', ['confirmed', 'pending_acknowledgement'])
                ->count(),
        ];
    }

    /**
     * Format version for API response.
     */
    protected function formatVersion(ActivityVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'title' => $version->title,
            'body' => $version->body,
            'tags' => json_decode($version->tags, true) ?: [],
            'state' => $version->state,
            'max_headcount' => $version->max_headcount,
            'signup_start' => $version->signup_start,
            'signup_end' => $version->signup_end,
            'eligibility_tags' => json_decode($version->eligibility_tags, true) ?: [],
            'required_supplies' => json_decode($version->required_supplies, true) ?: [],
            'published_at' => $version->published_at,
            'started_at' => $version->started_at ?? null,
            'completed_at' => $version->completed_at ?? null,
            'archived_at' => $version->archived_at ?? null,
            'created_at' => $version->created_at,
        ];
    }

    /**
     * Diff changes between versions.
     */
    protected function diffChanges(array $old, array $new): array
    {
        $changes = [];
        foreach ($new as $key => $value) {
            if ($old[$key] != $value) {
                $changes[$key] = [
                    'old' => $old[$key],
                    'new' => $value,
                ];
            }
        }
        return $changes;
    }
}