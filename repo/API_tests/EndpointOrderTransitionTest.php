<?php

declare(strict_types=1);

namespace tests\api;

use app\model\Order;

/**
 * HTTP endpoint tests for order state-transition routes:
 *   POST /api/v1/orders/:id/initiate-payment
 *   POST /api/v1/orders/:id/confirm-payment
 *   POST /api/v1/orders/:id/start-ticketing
 *   POST /api/v1/orders/:id/ticket
 *   POST /api/v1/orders/:id/refund
 *   POST /api/v1/orders/:id/close
 *   POST /api/v1/orders/:id/request-address-correction
 *   POST /api/v1/orders/:id/approve-address-correction
 *
 * Bootstrap roles used:
 *   administrator  — orders.* (all transition permissions)
 *   regular_user   — orders.read, orders.create only (no transition permissions)
 */
class EndpointOrderTransitionTest extends HttpTestCase
{
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupUsersLike('http-trans-%');
        $this->cleanupUsersLike('http-test-admin%');
        $this->cleanupTestOrders();

        $this->order = $this->createOrder('placed');
    }

    protected function tearDown(): void
    {
        $this->cleanupTestOrders();
        $this->cleanupUsersLike('http-trans-%');
        $this->cleanupUsersLike('http-test-admin%');
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // POST /api/v1/orders/:id/initiate-payment  (rbac: orders.payment)
    // ------------------------------------------------------------------

    public function testInitiatePaymentReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/initiate-payment', []);
        $this->assertUnauthorized($res);
    }

    public function testInitiatePaymentReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-trans-regular');
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/initiate-payment', []);
        $this->assertForbidden($res);
    }

    public function testInitiatePaymentAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/initiate-payment', []);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // POST /api/v1/orders/:id/confirm-payment  (rbac: orders.payment)
    // ------------------------------------------------------------------

    public function testConfirmPaymentReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/confirm-payment', []);
        $this->assertUnauthorized($res);
    }

    public function testConfirmPaymentReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-trans-regular');
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/confirm-payment', []);
        $this->assertForbidden($res);
    }

    public function testConfirmPaymentAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/confirm-payment', []);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // POST /api/v1/orders/:id/start-ticketing  (rbac: orders.ticketing)
    // ------------------------------------------------------------------

    public function testStartTicketingReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/start-ticketing', []);
        $this->assertUnauthorized($res);
    }

    public function testStartTicketingReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-trans-regular');
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/start-ticketing', []);
        $this->assertForbidden($res);
    }

    public function testStartTicketingAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/start-ticketing', []);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // POST /api/v1/orders/:id/ticket  (rbac: orders.ticketing)
    // ------------------------------------------------------------------

    public function testTicketReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/ticket', []);
        $this->assertUnauthorized($res);
    }

    public function testTicketReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-trans-regular');
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/ticket', []);
        $this->assertForbidden($res);
    }

    public function testTicketAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/ticket', []);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // POST /api/v1/orders/:id/refund  (rbac: orders.refund)
    // ------------------------------------------------------------------

    public function testRefundReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/refund', []);
        $this->assertUnauthorized($res);
    }

    public function testRefundReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-trans-regular');
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/refund', []);
        $this->assertForbidden($res);
    }

    public function testRefundAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/refund', []);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // POST /api/v1/orders/:id/close  (rbac: orders.close)
    // ------------------------------------------------------------------

    public function testCloseReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/close', []);
        $this->assertUnauthorized($res);
    }

    public function testCloseReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-trans-regular');
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/close', []);
        $this->assertForbidden($res);
    }

    public function testCloseAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/close', []);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // POST /api/v1/orders/:id/request-address-correction
    //   (rbac: orders.request_correction)
    // ------------------------------------------------------------------

    public function testRequestAddressCorrectionReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/request-address-correction', []);
        $this->assertUnauthorized($res);
    }

    public function testRequestAddressCorrectionReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-trans-regular');
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/request-address-correction', []);
        $this->assertForbidden($res);
    }

    public function testRequestAddressCorrectionAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/request-address-correction', []);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // POST /api/v1/orders/:id/approve-address-correction
    //   (rbac: orders.approve)
    // ------------------------------------------------------------------

    public function testApproveAddressCorrectionReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/approve-address-correction', []);
        $this->assertUnauthorized($res);
    }

    public function testApproveAddressCorrectionReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-trans-regular');
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/approve-address-correction', []);
        $this->assertForbidden($res);
    }

    public function testApproveAddressCorrectionAdminCanReachEndpoint(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/approve-address-correction', []);
        $this->assertNotEquals(401, $res['status'], 'Expected auth to pass but got 401');
        $this->assertNotEquals(403, $res['status'], 'Expected auth to pass but got 403');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createOrder(string $state): Order
    {
        $order = new Order();
        $order->activity_id   = 1;
        $order->created_by    = 1;
        $order->team_lead_id  = 1;
        $order->state         = $state;
        $order->items         = json_encode([]);
        $order->amount        = 0.0;
        // Prefix distinguishes these records for cleanup
        $order->ticket_number = 'http-trans-' . uniqid();
        $order->save();
        return $order;
    }

    private function cleanupTestOrders(): void
    {
        Order::where('ticket_number', 'like', 'http-trans-%')->delete();
    }
}
