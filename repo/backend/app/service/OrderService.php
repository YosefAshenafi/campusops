<?php

namespace app\service;

use app\model\Order;
use app\model\OrderStateHistory;
use app\model\ActivityGroup;

class OrderService
{
    protected AuditService $auditService;
    protected SearchService $searchService;

    public function __construct()
    {
        $this->auditService = new AuditService();
        $this->searchService = new SearchService();
    }

    const STATE_PLACED = 'placed';
    const STATE_PENDING_PAYMENT = 'pending_payment';
    const STATE_PAID = 'paid';
    const STATE_TICKETING = 'ticketing';
    const STATE_TICKETED = 'ticketed';
    const STATE_CANCELED = 'canceled';
    const STATE_CLOSED = 'closed';

    const STATES = ['placed', 'pending_payment', 'paid', 'ticketing', 'ticketed', 'canceled', 'closed'];

    /**
     * List orders with filters.
     */
    public function listOrders(int $page = 1, int $limit = 20, string $state = '', string $activityId = '', int $userId = 0, string $role = ''): array
    {
        $query = Order::order('id', 'desc');

        if (!empty($state)) {
            $query->where('state', $state);
        }

        if (!empty($activityId)) {
            $query->where('activity_id', $activityId);
        }

        if ($role !== 'administrator' && $role !== 'operations_staff' && $userId > 0) {
            $query->where('created_by', $userId);
        }

        $total = $query->count();
        $orders = $query->page($page, $limit)->select();

        $list = [];
        $activityIds = [];
        foreach ($orders as $o) {
            $list[] = $this->formatOrder($o);
            $activityIds[] = $o->activity_id;
        }

        // Batch-load activity titles to avoid N+1 queries
        $activityTitles = [];
        if (!empty($activityIds)) {
            $versions = \app\model\ActivityVersion::whereIn('group_id', array_unique($activityIds))
                ->order('version_number', 'desc')
                ->field('group_id, title')
                ->select();
            foreach ($versions as $v) {
                if (!isset($activityTitles[$v->group_id])) {
                    $activityTitles[$v->group_id] = $v->title;
                }
            }
        }

        foreach ($list as &$item) {
            $item['activity_title'] = $activityTitles[$item['activity_id']] ?? 'Activity #' . $item['activity_id'];
        }

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Get order by ID.
     */
    public function getOrder(int $id, int $userId = 0, string $role = ''): ?array
    {
        $order = Order::find($id);
        if (!$order) {
            return null;
        }

        if ($role !== 'administrator' && $role !== 'operations_staff' && $order->created_by !== $userId) {
            return null;
        }

        return $this->formatOrder($order);
    }

    /**
     * Get order state history with object-level authorization.
     */
    public function getHistory(int $orderId, int $userId = 0, string $role = ''): array
    {
        $order = Order::find($orderId);
        if (!$order) {
            throw new \Exception('Order not found', 404);
        }

        if ($role !== 'administrator' && $role !== 'operations_staff' && $role !== 'reviewer' && $order->created_by !== $userId) {
            throw new \Exception('Access denied', 403);
        }

        $history = OrderStateHistory::where('order_id', $orderId)
            ->order('id', 'desc')
            ->select();

        return array_map(fn($h) => [
            'id' => $h->id,
            'from_state' => $h->from_state,
            'to_state' => $h->to_state,
            'changed_by' => $h->changed_by,
            'notes' => $h->notes,
            'created_at' => $h->created_at,
        ], $history);
    }

    /**
     * Create a new order.
     */
    public function createOrder(array $data, $currentUser): array
    {
        if (empty($data['activity_id'])) {
            throw new \Exception('Activity ID is required', 400);
        }

        $activity = ActivityGroup::find($data['activity_id']);
        if (!$activity) {
            throw new \Exception('Activity not found', 404);
        }

        $order = new Order();
        $order->activity_id = $data['activity_id'];
        $order->created_by = $currentUser->id;
        $order->team_lead_id = $data['team_lead_id'] ?? $currentUser->id;
        $order->state = self::STATE_PLACED;
        $order->items = json_encode($data['items'] ?? []);
        $order->notes = $data['notes'] ?? '';
        $order->payment_method = $data['payment_method'] ?? '';
        $order->amount = $data['amount'] ?? 0;
        $order->save();

        $this->logStateChange($order->id, '', self::STATE_PLACED, $currentUser->id);
        $this->searchService->index('order', $order->id, 'Order #' . $order->id, $order->notes ?: '', []);

        return $this->formatOrder($order);
    }

    /**
     * Update order.
     */
    public function updateOrder(int $id, array $data, $currentUser): array
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception('Order not found', 404);
        }

        if ($order->state === self::STATE_CLOSED) {
            throw new \Exception('Cannot update closed order', 400);
        }

        if (isset($data['items'])) {
            $order->items = json_encode($data['items']);
        }
        if (isset($data['notes'])) {
            $order->notes = $data['notes'];
        }

        $order->save();

        return $this->formatOrder($order);
    }

    /**
     * Initiate payment (place order, start 30-min timer).
     */
    public function initiatePayment(int $id, $currentUser): array
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception('Order not found', 404);
        }

        if ($order->state !== self::STATE_PLACED) {
            throw new \Exception('Order must be in Placed state', 400);
        }

        $order->state = self::STATE_PENDING_PAYMENT;
        $order->auto_cancel_at = date('Y-m-d H:i:s', time() + 1800);
        $order->save();

        $this->logStateChange($order->id, self::STATE_PLACED, self::STATE_PENDING_PAYMENT, $currentUser->id);

        return $this->formatOrder($order);
    }

    /**
     * Confirm payment.
     */
    public function confirmPayment(int $id, array $data, $currentUser): array
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception('Order not found', 404);
        }

        if ($order->state !== self::STATE_PENDING_PAYMENT) {
            throw new \Exception('Order must be in Pending Payment state', 400);
        }

        $order->state = self::STATE_PAID;
        $order->payment_method = $data['payment_method'] ?? '';
        $order->amount = $data['amount'] ?? $order->amount;
        $order->auto_cancel_at = null;
        $order->save();

        $this->logStateChange($order->id, self::STATE_PENDING_PAYMENT, self::STATE_PAID, $currentUser->id);

        return $this->formatOrder($order);
    }

    /**
     * Start ticketing.
     */
    public function startTicketing(int $id, $currentUser): array
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception('Order not found', 404);
        }

        if ($order->state !== self::STATE_PAID) {
            throw new \Exception('Order must be Paid', 400);
        }

        $order->state = self::STATE_TICKETING;
        $order->save();

        $this->logStateChange($order->id, self::STATE_PAID, self::STATE_TICKETING, $currentUser->id);

        return $this->formatOrder($order);
    }

    /**
     * Add ticket number.
     */
    public function addTicket(int $id, string $ticketNumber, $currentUser): array
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception('Order not found', 404);
        }

        if ($order->state !== self::STATE_TICKETING) {
            throw new \Exception('Order must be in Ticketing state', 400);
        }

        $order->state = self::STATE_TICKETED;
        $order->ticket_number = $ticketNumber;
        $order->save();

        $this->logStateChange($order->id, self::STATE_TICKETING, self::STATE_TICKETED, $currentUser->id);

        return $this->formatOrder($order);
    }

    /**
     * Refund order (admin only).
     */
    public function refund(int $id, $currentUser): array
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception('Order not found', 404);
        }

        if ($order->state !== self::STATE_PAID) {
            throw new \Exception('Can only refund Paid orders', 400);
        }

        if ($currentUser->role !== 'administrator') {
            throw new \Exception('Only administrators can process refunds', 403);
        }

        $order->state = self::STATE_CANCELED;
        $order->save();

        $this->logStateChange($order->id, self::STATE_PAID, self::STATE_CANCELED, $currentUser->id, 'Refunded');

        return $this->formatOrder($order);
    }

    /**
     * Cancel order.
     */
    public function cancel(int $id, $currentUser): array
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception('Order not found', 404);
        }

        if (in_array($order->state, [self::STATE_TICKETED, self::STATE_CLOSED])) {
            throw new \Exception('Cannot cancel ' . $order->state . ' order', 400);
        }

        $previousState = $order->state;
        $order->state = self::STATE_CANCELED;
        $order->save();

        $this->logStateChange($order->id, $previousState, self::STATE_CANCELED, $currentUser->id);

        return $this->formatOrder($order);
    }

    /**
     * Close order.
     */
    public function close(int $id, $currentUser): array
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception('Order not found', 404);
        }

        if ($order->state !== self::STATE_TICKETED) {
            throw new \Exception('Order must be Ticketed', 400);
        }

        $order->state = self::STATE_CLOSED;
        $order->closed_at = date('Y-m-d H:i:s');
        $order->save();

        $this->logStateChange($order->id, self::STATE_TICKETED, self::STATE_CLOSED, $currentUser->id);

        return $this->formatOrder($order);
    }

    /**
     * Update address (closed orders require reviewer approval via request+approve flow).
     */
    public function updateAddress(int $id, array $data, $currentUser): array
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception('Order not found', 404);
        }

        if ($order->state === self::STATE_CLOSED) {
            throw new \Exception('Closed orders require address correction through the request+approve workflow. Use POST /orders/:id/request-address-correction instead.', 400);
        }

        if (!$currentUser->hasPermission('orders.update')) {
            throw new \Exception('Insufficient permissions', 403);
        }

        if (isset($data['invoice_address'])) {
            $order->invoice_address = EncryptionService::encrypt($data['invoice_address']);
        }

        $order->save();

        $this->auditService->log($currentUser->id, 'order', $id, 'address_update', '', '', ['method' => 'direct']);

        return $this->formatOrder($order);
    }

    /**
     * Request address correction for closed order - always requires reviewer approval.
     * All roles submit a request; only reviewers/admins can approve via approveAddressCorrection.
     */
    public function requestAddressCorrection(int $orderId, array $newAddress, int $requesterId, string $requesterRole): array
    {
        $order = Order::find($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        if ($order->state !== self::STATE_CLOSED) {
            return ['success' => false, 'message' => 'Only closed orders can request address correction'];
        }

        $pending = [
            'type' => 'address_correction',
            'requested_by' => $requesterId,
            'requester_role' => $requesterRole,
            'new_address' => $newAddress,
            'status' => 'pending_review',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $order->pending_address_correction = json_encode($pending);
        $order->save();

        $this->auditService->log($requesterId, 'order', $orderId, 'address_correction_requested', '', '', ['requester_role' => $requesterRole]);

        return ['success' => true, 'message' => 'Address correction request submitted for reviewer approval'];
    }

    /**
     * Approve address correction (reviewer/administrator only).
     */
    public function approveAddressCorrection(int $orderId, int $reviewerId, string $reviewerRole): array
    {
        if ($reviewerRole !== 'reviewer' && $reviewerRole !== 'administrator') {
            return ['success' => false, 'message' => 'Only reviewers or administrators can approve address corrections'];
        }

        $order = Order::find($orderId);
        if (!$order || !$order->pending_address_correction) {
            return ['success' => false, 'message' => 'No pending correction'];
        }

        $pending = json_decode($order->pending_address_correction, true);
        if (!$pending || $pending['status'] !== 'pending_review') {
            return ['success' => false, 'message' => 'Not pending'];
        }

        $order->invoice_address = EncryptionService::encrypt(json_encode($pending['new_address']));
        $order->pending_address_correction = null;
        $order->save();

        $this->auditService->log($reviewerId, 'order', $orderId, 'address_correction_approved', '', '', ['requested_by' => $pending['requested_by']]);

        return ['success' => true, 'message' => 'Address correction approved'];
    }

    /**
     * Log state change.
     */
    protected function logStateChange(int $orderId, string $fromState, string $toState, int $userId, string $notes = ''): void
    {
        $history = new OrderStateHistory();
        $history->order_id = $orderId;
        $history->from_state = $fromState;
        $history->to_state = $toState;
        $history->changed_by = $userId;
        $history->notes = $notes;
        $history->save();

        $this->auditService->log($userId, 'order', $orderId, 'state_change', $fromState, $toState, ['notes' => $notes]);
        \think\facade\Log::info("Order {$orderId} transitioned from '{$fromState}' to '{$toState}' by user {$userId}");
    }

    /**
     * Format order for API response.
     * invoice_address is never decrypted here; use formatOrderAdmin() for privileged contexts.
     */
    protected function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'activity_id' => $order->activity_id,
            'created_by' => $order->created_by,
            'team_lead_id' => $order->team_lead_id,
            'state' => $order->state,
            'items' => json_decode($order->items, true) ?: [],
            'notes' => $order->notes,
            'payment_method' => $order->payment_method,
            'amount' => $order->amount,
            'ticket_number' => $order->ticket_number,
            'auto_cancel_at' => $order->auto_cancel_at,
            'closed_at' => $order->closed_at,
            'invoice_address' => $order->invoice_address ? '***ENCRYPTED***' : null,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }

    /**
     * Format order for admin/privileged API response — decrypts invoice_address.
     */
    protected function formatOrderAdmin(Order $order): array
    {
        $data = $this->formatOrder($order);
        $data['invoice_address'] = $order->invoice_address ? EncryptionService::decrypt($order->invoice_address) : null;
        return $data;
    }
}