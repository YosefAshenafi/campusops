<?php

declare(strict_types=1);

namespace tests\services;

use app\model\Order;
use app\model\OrderStateHistory;
use app\service\OrderService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OrderService state machine enforcement.
 */
class OrderServiceTest extends TestCase
{
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrderService();
        Order::where('notes', 'unit-test-order')->delete();
    }

    protected function tearDown(): void
    {
        Order::where('notes', 'unit-test-order')->delete();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // cancel()
    // ------------------------------------------------------------------

    public function testCancelThrowsWhenOrderIsTicketed(): void
    {
        $order = $this->createOrder('ticketed');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);

        $user = $this->mockUser('administrator');
        $this->service->cancel($order->id, $user);
    }

    public function testCancelThrowsWhenOrderIsClosed(): void
    {
        $order = $this->createOrder('closed');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);

        $user = $this->mockUser('administrator');
        $this->service->cancel($order->id, $user);
    }

    public function testCancelSucceedsForPlacedOrder(): void
    {
        $order = $this->createOrder('placed');

        $user = $this->mockUser('administrator');
        $result = $this->service->cancel($order->id, $user);

        $this->assertEquals('canceled', $result['state']);
    }

    // ------------------------------------------------------------------
    // refund()
    // ------------------------------------------------------------------

    public function testRefundThrowsWhenCallerIsNotAdministrator(): void
    {
        $order = $this->createOrder('paid');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);

        $user = $this->mockUser('operations_staff');
        $this->service->refund($order->id, $user);
    }

    public function testRefundSucceedsForAdministrator(): void
    {
        $order = $this->createOrder('paid');

        $user = $this->mockUser('administrator');
        $result = $this->service->refund($order->id, $user);

        $this->assertEquals('canceled', $result['state']);
    }

    // ------------------------------------------------------------------
    // close()
    // ------------------------------------------------------------------

    public function testCloseThrowsWhenOrderIsNotTicketed(): void
    {
        $order = $this->createOrder('paid');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);

        $user = $this->mockUser('administrator');
        $this->service->close($order->id, $user);
    }

    public function testCloseSucceedsForTicketedOrder(): void
    {
        $order = $this->createOrder('ticketed');

        $user = $this->mockUser('administrator');
        $result = $this->service->close($order->id, $user);

        $this->assertEquals('closed', $result['state']);
    }

    // ------------------------------------------------------------------
    // cancelBySystem()
    // ------------------------------------------------------------------

    public function testCancelBySystemSetsStateToCanceled(): void
    {
        $order = $this->createOrder('pending_payment');

        $this->service->cancelBySystem($order->id, 'Auto-cancelled: payment not received within 30 minutes');

        $updated = Order::find($order->id);
        $this->assertEquals('canceled', $updated->state);
    }

    public function testCancelBySystemWritesHistoryWithChangedByZero(): void
    {
        $order = $this->createOrder('pending_payment');

        $this->service->cancelBySystem($order->id, 'Auto-cancelled: payment not received within 30 minutes');

        $history = OrderStateHistory::where('order_id', $order->id)
            ->where('to_state', 'canceled')
            ->find();

        $this->assertNotNull($history);
        $this->assertEquals(0, $history->changed_by);
        $this->assertEquals('pending_payment', $history->from_state);
    }

    public function testCancelBySystemThrowsWhenOrderIsNotPendingPayment(): void
    {
        $order = $this->createOrder('placed');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);

        $this->service->cancelBySystem($order->id, 'reason');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createOrder(string $state): Order
    {
        $order = new Order();
        $order->activity_id = 1;
        $order->created_by = 1;
        $order->team_lead_id = 1;
        $order->state = $state;
        $order->items = json_encode([]);
        $order->notes = 'unit-test-order';
        $order->amount = 0;
        $order->save();

        return $order;
    }

    private function mockUser(string $role): object
    {
        return new class($role) {
            public int $id = 1;
            public string $role;

            public function __construct(string $role)
            {
                $this->role = $role;
            }

            public function hasPermission(string $permission): bool
            {
                return true;
            }
        };
    }
}
