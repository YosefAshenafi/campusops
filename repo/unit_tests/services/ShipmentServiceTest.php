<?php

declare(strict_types=1);

namespace tests\services;

use app\model\Shipment;
use app\model\ScanEvent;
use app\model\ShipmentException;
use app\model\Order;
use app\service\ShipmentService;
use PHPUnit\Framework\TestCase;

class ShipmentServiceTest extends TestCase
{
    private ShipmentService $service;
    private static int $testOrderId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShipmentService();
        $this->ensureTestOrder();
    }

    protected function tearDown(): void
    {
        $shipmentIds = Shipment::where('order_id', self::$testOrderId)->column('id');
        if (!empty($shipmentIds)) {
            ScanEvent::whereIn('shipment_id', $shipmentIds)->delete();
            ShipmentException::whereIn('shipment_id', $shipmentIds)->delete();
        }
        Shipment::where('order_id', self::$testOrderId)->delete();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Class loading (parse error gate)
    // ------------------------------------------------------------------

    public function testShipmentServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ShipmentService::class, $this->service);
    }

    // ------------------------------------------------------------------
    // createShipment
    // ------------------------------------------------------------------

    public function testCreateShipmentReturnsValidStructure(): void
    {
        $user = $this->mockUser('operations_staff', 2);
        $result = $this->service->createShipment(self::$testOrderId, [
            'carrier' => 'FedEx',
            'tracking_number' => 'TK-TEST-001',
            'package_contents' => ['item1'],
            'weight' => 5.5,
        ], $user);

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(self::$testOrderId, $result['order_id']);
        $this->assertEquals('FedEx', $result['carrier']);
        $this->assertEquals('TK-TEST-001', $result['tracking_number']);
        $this->assertEquals('created', $result['status']);
    }

    // ------------------------------------------------------------------
    // getShipment
    // ------------------------------------------------------------------

    public function testGetShipmentReturnsShipment(): void
    {
        $shipment = $this->createTestShipment();
        $result = $this->service->getShipment($shipment->id, 1, 'administrator');

        $this->assertEquals($shipment->id, $result['id']);
    }

    public function testGetShipmentThrows404ForNonexistent(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->getShipment(999999, 1, 'administrator');
    }

    // ------------------------------------------------------------------
    // processScan
    // ------------------------------------------------------------------

    public function testProcessScanCreatesScanEvent(): void
    {
        $shipment = $this->createTestShipment();
        $user = $this->mockUser('operations_staff', 2);

        $result = $this->service->processScan($shipment->id, 'SCAN-001', $user);

        $this->assertArrayHasKey('scan_event_id', $result);
        $this->assertEquals('scanned', $result['result']);
    }

    // ------------------------------------------------------------------
    // confirmDelivery
    // ------------------------------------------------------------------

    public function testConfirmDeliveryUpdatesStatus(): void
    {
        $shipment = $this->createTestShipment();
        $user = $this->mockUser('operations_staff', 2);

        $result = $this->service->confirmDelivery($shipment->id, $user);

        $this->assertEquals('delivered', $result['status']);
    }

    // ------------------------------------------------------------------
    // reportException
    // ------------------------------------------------------------------

    public function testReportExceptionUpdatesStatusToException(): void
    {
        $shipment = $this->createTestShipment();
        $user = $this->mockUser('operations_staff', 2);

        $result = $this->service->reportException($shipment->id, [
            'description' => 'Package damaged',
        ], $user);

        $this->assertArrayHasKey('id', $result);
        $updated = Shipment::find($shipment->id);
        $this->assertEquals('exception', $updated->status);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function ensureTestOrder(): void
    {
        $order = Order::where('notes', 'shipment-test-order')->find();
        if (!$order) {
            $order = new Order();
            $order->activity_id = 1;
            $order->created_by = 2;
            $order->team_lead_id = 4;
            $order->state = 'paid';
            $order->items = json_encode([]);
            $order->notes = 'shipment-test-order';
            $order->save();
        }
        self::$testOrderId = $order->id;
    }

    private function createTestShipment(): Shipment
    {
        $s = new Shipment();
        $s->order_id = self::$testOrderId;
        $s->carrier = 'TestCarrier';
        $s->tracking_number = 'TK-' . rand(1000, 9999);
        $s->package_contents = json_encode([]);
        $s->weight = 1.0;
        $s->status = 'created';
        $s->save();
        return $s;
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
