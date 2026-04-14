<?php

namespace app\service;

use app\model\Dashboard;
use app\model\Order;
use app\model\ActivityGroup;
use app\model\ActivityVersion;

class DashboardService
{
    public function getDefault(int $userId): array
    {
        $ordersByState = Order::field('state, count(*) as count')
            ->group('state')
            ->select()
            ->toArray();

        $activitiesByState = ActivityVersion::field('state, count(*) as count')
            ->group('state')
            ->select()
            ->toArray();

        $recentOrders = Order::order('id', 'desc')->limit(5)->select();

        $recentOrdersList = [];
        foreach ($recentOrders as $o) {
            $recentOrdersList[] = [
                'id' => $o->id,
                'state' => $o->state,
                'amount' => $o->amount,
            ];
        }

        return [
            'widgets' => [
                'orders_by_state' => $ordersByState,
                'activities_by_state' => $activitiesByState,
                'recent_orders' => $recentOrdersList,
            ],
        ];
    }

    public function getCustom(int $userId): array
    {
        $dashboard = Dashboard::where('user_id', $userId)->order('id', 'desc')->find();
        if (!$dashboard) {
            return ['layout' => null, 'name' => null, 'widgets' => []];
        }
        $result = $dashboard->toArray();
        // Ensure layout field is always present for frontend compatibility
        if (!isset($result['layout'])) {
            $result['layout'] = json_decode($dashboard->widgets, true) ?: [];
        }
        return $result;
    }

    public function saveCustom(int $userId, array $data): array
    {
        // Accept both layout (from frontend) and name/widgets (from API)
        $layout = $data['layout'] ?? null;
        $name = $data['name'] ?? 'custom';
        $widgets = $layout ? $layout : ($data['widgets'] ?? []);

        // Upsert: update existing or create new
        $dashboard = Dashboard::where('user_id', $userId)->find();
        if (!$dashboard) {
            $dashboard = new Dashboard();
            $dashboard->user_id = $userId;
        }
        $dashboard->name = $name;
        $dashboard->widgets = json_encode($widgets);
        $dashboard->is_default = $data['is_default'] ?? false;
        $dashboard->save();

        $result = $dashboard->toArray();
        $result['layout'] = $widgets;
        return $result;
    }

    public function deleteCustom(int $userId): void
    {
        Dashboard::where('user_id', $userId)->delete();
    }

    public function updateCustom(int $id, int $userId, array $data): array
    {
        $dashboard = Dashboard::find($id);
        if (!$dashboard || $dashboard->user_id != $userId) {
            throw new \Exception('Dashboard not found', 404);
        }

        if (isset($data['name'])) $dashboard->name = $data['name'];
        if (isset($data['widgets'])) $dashboard->widgets = json_encode($data['widgets']);
        $dashboard->save();

        return $dashboard->toArray();
    }

    /**
     * Favorite a widget for a user.
     */
    public function favoriteWidget(int $userId, string $widgetId): array
    {
        $existing = \app\model\DashboardFavorite::where('user_id', $userId)
            ->where('widget_id', $widgetId)
            ->find();
        if (!$existing) {
            $fav = new \app\model\DashboardFavorite();
            $fav->user_id = $userId;
            $fav->widget_id = $widgetId;
            $fav->save();
        }
        return ['widget_id' => $widgetId, 'favorited' => true];
    }

    /**
     * Unfavorite a widget for a user.
     */
    public function unfavoriteWidget(int $userId, string $widgetId): array
    {
        \app\model\DashboardFavorite::where('user_id', $userId)
            ->where('widget_id', $widgetId)
            ->delete();
        return ['widget_id' => $widgetId, 'favorited' => false];
    }

    /**
     * Get all favorited widgets for a user.
     */
    public function getFavorites(int $userId): array
    {
        $favorites = \app\model\DashboardFavorite::where('user_id', $userId)->select();
        $result = [];
        foreach ($favorites as $f) {
            $result[] = ['widget_id' => $f->widget_id];
        }
        return $result;
    }

    /**
     * Get drill-through data for a specific widget.
     */
    public function getDrillData(string $widgetId): array
    {
        if ($widgetId === 'orders_by_state') {
            $orders = Order::order('id', 'desc')->limit(50)->select();
            $list = [];
            foreach ($orders as $o) {
                $list[] = ['id' => $o->id, 'state' => $o->state, 'amount' => $o->amount, 'created_at' => $o->created_at];
            }
            return ['widget_id' => $widgetId, 'data' => $list];
        }
        if ($widgetId === 'activities_by_state') {
            $activities = ActivityVersion::order('id', 'desc')->limit(50)->select()->toArray();
            return ['widget_id' => $widgetId, 'data' => $activities];
        }
        return ['widget_id' => $widgetId, 'data' => []];
    }
}