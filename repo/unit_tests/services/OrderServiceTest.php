<?php

declare(strict_types=1);

namespace tests\services;

use app\model\Order;
use app\model\OrderStateHistory;
use app\model\ActivityGroup;
use app\service\OrderService;
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrderService();
        Order::where('notes', 'unit-test-order')->delete();
        OrderStateHistory::where('notes', 'unit-test-order')->delete();
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
    // createOrder()
    // ------------------------------------------------------------------

    public function testCreateOrderReturnsPlacedOrder(): void
    {
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        $user = $this->mockUser('administrator');
        $result = $this->service->createOrder([
            'activity_id' => $group->id,
            'notes' => 'unit-test-order',
        ], $user);

        $this->assertEquals('placed', $result['state']);
        $this->assertEquals($group->id, $result['activity_id']);
    }

    public function testCreateOrderThrowsWhenActivityIdMissing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $user = $this->mockUser('administrator');
        $this->service->createOrder(['notes' => 'unit-test-order'], $user);
    }

    public function testCreateOrderThrowsWhenActivityNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $user = $this->mockUser('administrator');
        $this->service->createOrder(['activity_id' => 999999, 'notes' => 'unit-test-order'], $user);
    }

    // ------------------------------------------------------------------
    // initiatePayment / confirmPayment
    // ------------------------------------------------------------------

    public function testInitiatePaymentTransitionsToPlacedToPending(): void
    {
        $order = $this->createOrder('placed');
        $user = $this->mockUser('administrator');
        $result = $this->service->initiatePayment($order->id, $user);

        $this->assertEquals('pending_payment', $result['state']);
        $this->assertNotNull($result['auto_cancel_at']);
    }

    public function testInitiatePaymentThrowsWhenNotPlaced(): void
    {
        $order = $this->createOrder('paid');
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $user = $this->mockUser('administrator');
        $this->service->initiatePayment($order->id, $user);
    }

    public function testConfirmPaymentTransitionsToPaid(): void
    {
        $order = $this->createOrder('pending_payment');
        $user = $this->mockUser('administrator');
        $result = $this->service->confirmPayment($order->id, [
            'payment_method' => 'wechat',
            'amount' => 100,
        ], $user);

        $this->assertEquals('paid', $result['state']);
    }

    // ------------------------------------------------------------------
    // startTicketing / addTicket
    // ------------------------------------------------------------------

    public function testStartTicketingTransitionsPaidToTicketing(): void
    {
        $order = $this->createOrder('paid');
        $user = $this->mockUser('administrator');
        $result = $this->service->startTicketing($order->id, $user);
        $this->assertEquals('ticketing', $result['state']);
    }

    public function testAddTicketTransitionsTicketingToTicketed(): void
    {
        $order = $this->createOrder('ticketing');
        $user = $this->mockUser('administrator');
        $result = $this->service->addTicket($order->id, 'TK-12345', $user);

        $this->assertEquals('ticketed', $result['state']);
        $this->assertEquals('TK-12345', $result['ticket_number']);
    }

    // ------------------------------------------------------------------
    // getHistory()
    // ------------------------------------------------------------------

    public function testGetHistoryReturnsStateChanges(): void
    {
        $order = $this->createOrder('placed');
        $user = $this->mockUser('administrator');
        $this->service->cancel($order->id, $user);

        $history = $this->service->getHistory($order->id, $user->id, 'administrator');

        $this->assertNotEmpty($history);
        $this->assertEquals('canceled', $history[0]['to_state']);
    }

    public function testGetHistoryThrows403ForUnauthorizedUser(): void
    {
        $order = $this->createOrder('placed');
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->service->getHistory($order->id, 999, 'regular_user');
    }

    // ------------------------------------------------------------------
    // getOrder()
    // ------------------------------------------------------------------

    public function testGetOrderReturnsNullForUnauthorizedUser(): void
    {
        $order = $this->createOrder('placed');
        $result = $this->service->getOrder($order->id, 999, 'regular_user');
        $this->assertNull($result);
    }

    public function testGetOrderReturnsOrderForOwner(): void
    {
        $order = $this->createOrder('placed');
        $result = $this->service->getOrder($order->id, 1, 'regular_user');
        $this->assertNotNull($result);
        $this->assertEquals($order->id, $result['id']);
    }

    // ------------------------------------------------------------------
    // updateOrder()
    // ------------------------------------------------------------------

    public function testUpdateOrderModifiesNotes(): void
    {
        $order = $this->createOrder('placed');
        $user = $this->mockUser('administrator');
        $result = $this->service->updateOrder($order->id, ['notes' => 'updated-notes'], $user);
        $this->assertEquals('updated-notes', $result['notes']);
    }

    public function testUpdateOrderThrowsForClosedOrder(): void
    {
        $order = $this->createOrder('closed');
        $user = $this->mockUser('administrator');
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->updateOrder($order->id, ['notes' => 'nope'], $user);
    }

    // ------------------------------------------------------------------
    // requestAddressCorrection / approveAddressCorrection
    // ------------------------------------------------------------------

    public function testRequestAddressCorrectionOnClosedOrder(): void
    {
        $order = $this->createOrder('closed');
        $result = $this->service->requestAddressCorrection(
            $order->id, ['street' => '123 Test St'], 1, 'regular_user'
        );
        $this->assertTrue($result['success']);
    }

    public function testRequestAddressCorrectionFailsOnNonClosedOrder(): void
    {
        $order = $this->createOrder('placed');
        $result = $this->service->requestAddressCorrection(
            $order->id, ['street' => '123 Test St'], 1, 'regular_user'
        );
        $this->assertFalse($result['success']);
    }

    public function testApproveAddressCorrectionRequiresReviewerRole(): void
    {
        $order = $this->createOrder('closed');
        $this->service->requestAddressCorrection(
            $order->id, ['street' => '123 Test St'], 1, 'regular_user'
        );

        $result = $this->service->approveAddressCorrection($order->id, 2, 'regular_user');
        $this->assertFalse($result['success']);
    }

    public function testApproveAddressCorrectionSucceedsForReviewer(): void
    {
        $order = $this->createOrder('closed');
        $this->service->requestAddressCorrection(
            $order->id, ['street' => '123 Test St'], 1, 'regular_user'
        );

        $result = $this->service->approveAddressCorrection($order->id, 2, 'reviewer');
        $this->assertTrue($result['success']);
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
            public function __construct(string $role) { $this->role = $role; }
            public function hasPermission(string $permission): bool { return true; }
        };
    }
}
