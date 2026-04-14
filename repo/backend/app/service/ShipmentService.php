<?php

namespace app\service;

use app\model\Shipment;
use app\model\ScanEvent;
use app\model\ShipmentException;
use app\model\Order;
{
    const STATUS_CREATED = 'created';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_EXCEPTION = 'exception';

    /**
     * List all shipments with pagination and optional status filter.
     */
    public function listAll(int $page = 1, int $limit = 20, string $status = ''): array
    {
        $query = Shipment::order('id', 'desc');

        if (!empty($status)) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $shipments = $query->page($page, $limit)->select();

        return [
            'list' => array_map(fn($s) => $this->formatShipment($s), $shipments),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Get shipments by order.
     */
    public function getByOrder(int $orderId): array
    {
        $shipments = Shipment::where('order_id', $orderId)->select();

        return array_map(fn($s) => $this->formatShipment($s), $shipments);
    }

    /**
     * Get shipment by ID.
     */
    public function getShipment(int $id, int $userId = 0, string $role = ''): array
    {
        $shipment = Shipment::find($id);
        if (!$shipment) {
            throw new \Exception('Shipment not found', 404);
        }

        if ($role !== 'administrator' && $role !== 'operations_staff') {
            $order = Order::find($shipment->order_id);
            if ($order && $order->created_by !== $userId) {
                throw new \Exception('Access denied', 403);
            }
        }

        return $this->formatShipment($shipment);
    }

    /**
     * Create shipment.
     */
    public function createShipment(int $orderId, array $data, $currentUser): array
    {
        $shipment = new Shipment();
        $shipment->order_id = $orderId;
        $shipment->carrier = $data['carrier'] ?? '';
        $shipment->tracking_number = $data['tracking_number'] ?? '';
        $shipment->package_contents = json_encode($data['package_contents'] ?? []);
        $shipment->weight = $data['weight'] ?? 0;
        $shipment->status = self::STATUS_CREATED;
        $shipment->save();

        return $this->formatShipment($shipment);
    }

    /**
     * Process scan event.
     */
    public function processScan(int $shipmentId, string $scanCode, $currentUser): array
    {
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            throw new \Exception('Shipment not found', 404);
        }

        $scanEvent = new ScanEvent();
        $scanEvent->shipment_id = $shipmentId;
        $scanEvent->scan_code = $scanCode;
        $scanEvent->location = 'unknown';
        $scanEvent->scanned_by = $currentUser->id;
        $scanEvent->result = 'scanned';
        $scanEvent->save();

        if (in_array($shipment->status, [self::STATUS_CREATED, self::STATUS_IN_TRANSIT])) {
            $shipment->status = self::STATUS_IN_TRANSIT;
            $shipment->save();
        }

        return [
            'scan_event_id' => $scanEvent->id,
            'result' => $scanEvent->result,
            'shipment_status' => $shipment->status,
        ];
    }

    /**
     * Get scan history.
     */
    public function getScanHistory(int $shipmentId): array
    {
        $events = ScanEvent::where('shipment_id', $shipmentId)
            ->order('id', 'desc')
            ->select();

        return array_map(fn($e) => [
            'id' => $e->id,
            'scan_code' => $e->scan_code,
            'location' => $e->location,
            'result' => $e->result,
            'created_at' => $e->created_at,
        ], $events);
    }

    /**
     * Confirm delivery and emit arrival_reminder notification to the order owner.
     */
    public function confirmDelivery(int $shipmentId, $currentUser): array
    {
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            throw new \Exception('Shipment not found', 404);
        }

        $shipment->status = self::STATUS_DELIVERED;
        $shipment->save();

        // Notify the order owner that their shipment has arrived
        $order = Order::find($shipment->order_id);
        if ($order && $order->created_by) {
            try {
                $notificationService = new NotificationService();
                $notificationService->create(
                    $order->created_by,
                    'arrival_reminder',
                    'Shipment Arrived',
                    'Your shipment for Order #' . $shipment->order_id . ' has been delivered.',
                    'shipment',
                    $shipmentId
                );
            } catch (\Exception $e) {
                // Notification suppressed by user preference — delivery still succeeds
            }
        }

        return $this->formatShipment($shipment);
    }

    /**
     * Get exceptions.
     */
    public function getExceptions(int $shipmentId): array
    {
        $exceptions = ShipmentException::where('shipment_id', $shipmentId)
            ->order('id', 'desc')
            ->select();

        return array_map(fn($e) => [
            'id' => $e->id,
            'description' => $e->description,
            'reported_by' => $e->reported_by,
            'created_at' => $e->created_at,
        ], $exceptions);
    }

    /**
     * Report exception.
     */
    public function reportException(int $shipmentId, array $data, $currentUser): array
    {
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            throw new \Exception('Shipment not found', 404);
        }

        $exception = new ShipmentException();
        $exception->shipment_id = $shipmentId;
        $exception->description = $data['description'] ?? '';
        $exception->reported_by = $currentUser->id;
        $exception->save();

        $shipment->status = self::STATUS_EXCEPTION;
        $shipment->save();

        return [
            'id' => $exception->id,
            'description' => $exception->description,
            'created_at' => $exception->created_at,
        ];
    }

    /**
     * Format shipment for API response.
     */
    protected function formatShipment(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'carrier' => $shipment->carrier,
            'tracking_number' => $shipment->tracking_number,
            'package_contents' => json_decode($shipment->package_contents, true) ?: [],
            'weight' => $shipment->weight,
            'status' => $shipment->status,
            'origin' => $shipment->origin ?? '',
            'destination' => $shipment->destination ?? '',
            'created_at' => $shipment->created_at,
        ];
    }
}