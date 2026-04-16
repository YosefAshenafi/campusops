<?php

namespace app\service;

use app\model\Notification;
use app\model\UserPreference;

class NotificationService
{
    /**
     * Get user notifications.
     */
    public function getNotifications(int $userId, int $page = 1, int $limit = 20): array
    {
        $query = Notification::where('user_id', $userId)->order('id', 'desc');
        $total = $query->count();
        $notifications = $query->page($page, $limit)->select();

        $unreadCount = Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return [
            'list' => array_map(fn($n) => $this->format($n), $notifications->all()),
            'total' => $total,
            'unread_count' => $unreadCount,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Create notification (for internal use).
     */
    public function create(int $userId, string $type, string $title, string $body, string $entityType = '', int $entityId = 0): Notification
    {
        $prefs = $this->getSettings($userId);
        $enabled = $this->isEnabled($prefs, $type);
        
        if (!$enabled) {
            throw new \Exception('Notifications of this type are disabled');
        }

        $notification = new Notification();
        $notification->user_id = $userId;
        $notification->type = $type;
        $notification->title = $title;
        $notification->body = $body;
        $notification->entity_type = $entityType;
        $notification->entity_id = $entityId;
        $notification->save();

        return $notification;
    }

    /**
     * Mark notification as read.
     */
    public function markRead(int $id, int $userId): void
    {
        $notification = Notification::find($id);
        if (!$notification || $notification->user_id != $userId) {
            throw new \Exception('Notification not found', 404);
        }
        $notification->read_at = date('Y-m-d H:i:s');
        $notification->save();
    }

    /**
     * Get notification settings.
     */
    public function getSettings(int $userId): array
    {
        $prefs = UserPreference::where('user_id', $userId)->find();
        
        if (!$prefs) {
            return [
                'arrival_reminders' => true,
                'activity_alerts' => true,
                'order_alerts' => true,
                'violation_alerts' => true,
            ];
        }

        return [
            'arrival_reminders' => (bool) $prefs->arrival_reminders,
            'activity_alerts' => (bool) $prefs->activity_alerts,
            'order_alerts' => (bool) $prefs->order_alerts,
            'violation_alerts' => (bool) $prefs->violation_alerts,
            'dashboard_layout' => $prefs->dashboard_layout !== null ? json_decode($prefs->dashboard_layout, true) : null,
        ];
    }

    /**
     * Update notification settings.
     */
    public function updateSettings(int $userId, array $data): void
    {
        $prefs = UserPreference::where('user_id', $userId)->find();
        
        if (!$prefs) {
            $prefs = new UserPreference();
            $prefs->user_id = $userId;
        }

        if (isset($data['arrival_reminders'])) {
            $prefs->arrival_reminders = $data['arrival_reminders'] ? 1 : 0;
        }
        if (isset($data['activity_alerts'])) {
            $prefs->activity_alerts = $data['activity_alerts'] ? 1 : 0;
        }
        if (isset($data['order_alerts'])) {
            $prefs->order_alerts = $data['order_alerts'] ? 1 : 0;
        }
        if (isset($data['violation_alerts'])) {
            $prefs->violation_alerts = $data['violation_alerts'] ? 1 : 0;
        }
        if (isset($data['dashboard_layout'])) {
            $prefs->dashboard_layout = json_encode($data['dashboard_layout']);
        }

        $prefs->save();
    }

    protected function isEnabled(array $prefs, string $type): bool
    {
        return match($type) {
            'arrival_reminder' => $prefs['arrival_reminders'] ?? true,
            'activity_update' => $prefs['activity_alerts'] ?? true,
            'order_update' => $prefs['order_alerts'] ?? true,
            'violation_alert' => $prefs['violation_alerts'] ?? true,
            default => true,
        };
    }

    protected function format(Notification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'entity_type' => $n->entity_type,
            'entity_id' => $n->entity_id,
            'read_at' => $n->read_at,
            'created_at' => $n->created_at,
        ];
    }
}