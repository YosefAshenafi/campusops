<?php

declare(strict_types=1);

namespace tests\api;

use app\model\Order;
use app\model\User;
use app\service\AuthService;
use app\service\OrderService;
use PHPUnit\Framework\TestCase;

/**
 * API integration tests for order visibility based on role.
 */
class OrderApiTest extends TestCase
{
    private OrderService $orderService;
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = new OrderService();
        $this->authService  = new AuthService();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Reviewer can see all orders
    // ------------------------------------------------------------------

    public function testReviewerCanListAllOrders(): void
    {
        // Create an order owned by user_id = 1 (admin seed user)
        $order = $this->createOrder(1);

        // Log in as reviewer
        $reviewer = $this->ensureUser('api-test-reviewer', 'reviewer');
        $result = $this->orderService->listOrders(1, 20, '', '', $reviewer->id, $reviewer->role);

        $ids = array_column($result['list'], 'id');
        $this->assertContains($order->id, $ids, 'Reviewer should see all orders, not just their own');
    }

    public function testReviewerCanGetOrderByIdNotOwnedByThem(): void
    {
        $order = $this->createOrder(1);

        $reviewer = $this->ensureUser('api-test-reviewer', 'reviewer');
        $result = $this->orderService->getOrder($order->id, $reviewer->id, $reviewer->role);

        $this->assertNotNull($result, 'Reviewer should be able to fetch any order by ID');
        $this->assertEquals($order->id, $result['id']);
    }

    // ------------------------------------------------------------------
    // Regular user can only see their own orders
    // ------------------------------------------------------------------

    public function testRegularUserCannotSeeOtherUsersOrders(): void
    {
        // Order owned by user_id = 1
        $order = $this->createOrder(1);

        // Regular user with a different ID
        $regularUser = $this->ensureUser('api-test-regular', 'regular_user');
        $result = $this->orderService->listOrders(1, 20, '', '', $regularUser->id, $regularUser->role);

        $ids = array_column($result['list'], 'id');
        $this->assertNotContains($order->id, $ids, 'Regular user should not see orders they did not create');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createOrder(int $createdBy): Order
    {
        $order = new Order();
        $order->activity_id  = 1;
        $order->created_by   = $createdBy;
        $order->team_lead_id = $createdBy;
        $order->state        = 'placed';
        $order->items        = json_encode([]);
        $order->notes        = 'api-test-order';
        $order->amount       = 0;
        $order->save();

        return $order;
    }

    private function ensureUser(string $username, string $role): User
    {
        $user = User::where('username', $username)->find();
        if (!$user) {
            $user = new User();
            $user->username = $username;
            $user->role     = $role;
            $user->status   = 'active';
            $user->setPassword('TestPass1234');
            $user->save();
        }
        return $user;
    }

    private function cleanUp(): void
    {
        Order::where('notes', 'api-test-order')->delete();
        User::where('username', 'api-test-reviewer')->delete();
        User::where('username', 'api-test-regular')->delete();
    }
}
