<?php

declare(strict_types=1);

namespace tests\api;

use app\model\Dashboard;

/**
 * HTTP endpoint tests for dashboard sub-routes not covered by EndpointMiscTest:
 *
 *   GET    /api/v1/dashboard/custom
 *   POST   /api/v1/dashboard/custom
 *   PUT    /api/v1/dashboard/custom/:id
 *   DELETE /api/v1/dashboard/custom
 *   POST   /api/v1/dashboard/favorites
 *   DELETE /api/v1/dashboard/favorites/:widget_id
 *   GET    /api/v1/dashboard/drill/:widget_id
 *   GET    /api/v1/dashboard/snapshot
 */
class EndpointDashboardExtTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->token = '';
        $this->cleanupUsersLike('http-dash-%');
    }

    protected function tearDown(): void
    {
        $this->token = '';
        $this->cleanupUsersLike('http-dash-%');
        parent::tearDown();
    }

    private function loginAdmin(): void
    {
        $this->loginAsRole('administrator', 'http-dash-admin');
    }

    private function loginRegular(): void
    {
        $this->loginAsRole('regular_user', 'http-dash-regular');
    }

    // ======================================================================
    // GET /api/v1/dashboard/custom
    // ======================================================================

    public function testGetCustomDashboardReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/dashboard/custom');
        $this->assertUnauthorized($res);
    }

    public function testGetCustomDashboardReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/dashboard/custom');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testGetCustomDashboardReturns200ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->get('/api/v1/dashboard/custom');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // POST /api/v1/dashboard/custom
    // ======================================================================

    public function testCreateCustomDashboardReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/dashboard/custom', ['name' => 'My Custom Dashboard', 'widgets' => []]);
        $this->assertUnauthorized($res);
    }

    public function testCreateCustomDashboardReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->post('/api/v1/dashboard/custom', ['name' => 'My Custom Dashboard', 'widgets' => []]);
        $this->assertForbidden($res);
    }

    public function testCreateCustomDashboardReturns201ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->post('/api/v1/dashboard/custom', [
            'name'    => 'Test Custom Dashboard',
            'widgets' => ['orders_by_state', 'activities_by_state'],
        ]);
        $this->assertStatus(201, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // PUT /api/v1/dashboard/custom/:id
    // ======================================================================

    public function testUpdateCustomDashboardReturns401WhenUnauthenticated(): void
    {
        $res = $this->put('/api/v1/dashboard/custom/1', ['name' => 'Updated']);
        $this->assertUnauthorized($res);
    }

    public function testUpdateCustomDashboardReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->put('/api/v1/dashboard/custom/1', ['name' => 'Updated']);
        $this->assertForbidden($res);
    }

    public function testUpdateCustomDashboardReturns404ForMissingId(): void
    {
        $this->loginAdmin();
        $res = $this->put('/api/v1/dashboard/custom/999999', ['name' => 'Ghost Dashboard']);
        $this->assertNotFound($res);
    }

    public function testUpdateCustomDashboardReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        // Create one first so we have a real id to update.
        $create = $this->post('/api/v1/dashboard/custom', [
            'name'    => 'DashToUpdate',
            'widgets' => [],
        ]);
        $dashId = $create['body']['data']['id'] ?? 0;
        if ($dashId === 0) {
            $this->markTestSkipped('Could not create a custom dashboard to update.');
        }

        $res = $this->put("/api/v1/dashboard/custom/{$dashId}", ['name' => 'Updated Name']);
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // DELETE /api/v1/dashboard/custom
    // ======================================================================

    public function testDeleteCustomDashboardReturns401WhenUnauthenticated(): void
    {
        $res = $this->delete('/api/v1/dashboard/custom');
        $this->assertUnauthorized($res);
    }

    public function testDeleteCustomDashboardReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->delete('/api/v1/dashboard/custom');
        $this->assertForbidden($res);
    }

    public function testDeleteCustomDashboardReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->delete('/api/v1/dashboard/custom');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // POST /api/v1/dashboard/favorites
    // ======================================================================

    public function testAddFavoriteReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/dashboard/favorites', ['widget_id' => 'orders_by_state']);
        $this->assertUnauthorized($res);
    }

    public function testAddFavoriteReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->post('/api/v1/dashboard/favorites', ['widget_id' => 'orders_by_state']);
        $this->assertForbidden($res);
    }

    public function testAddFavoriteReturns400WhenWidgetIdMissing(): void
    {
        $this->loginAdmin();
        $res = $this->post('/api/v1/dashboard/favorites', []);
        $this->assertStatus(400, $res);
    }

    public function testAddFavoriteReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->post('/api/v1/dashboard/favorites', ['widget_id' => 'orders_by_state']);
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // DELETE /api/v1/dashboard/favorites/:widget_id
    // ======================================================================

    public function testRemoveFavoriteReturns401WhenUnauthenticated(): void
    {
        $res = $this->delete('/api/v1/dashboard/favorites/orders_by_state');
        $this->assertUnauthorized($res);
    }

    public function testRemoveFavoriteReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->delete('/api/v1/dashboard/favorites/orders_by_state');
        $this->assertForbidden($res);
    }

    public function testRemoveFavoriteReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->delete('/api/v1/dashboard/favorites/orders_by_state');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // GET /api/v1/dashboard/drill/:widget_id
    // ======================================================================

    public function testDrillReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/dashboard/drill/orders_by_state');
        $this->assertUnauthorized($res);
    }

    public function testDrillReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/dashboard/drill/orders_by_state');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testDrillReturns200ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->get('/api/v1/dashboard/drill/orders_by_state');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // GET /api/v1/dashboard/snapshot
    // ======================================================================

    public function testSnapshotReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/dashboard/snapshot');
        $this->assertUnauthorized($res);
    }

    public function testSnapshotReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->get('/api/v1/dashboard/snapshot');
        $this->assertForbidden($res);
    }

    public function testSnapshotReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/dashboard/snapshot');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
        $data = $res['body']['data'] ?? [];
        $this->assertArrayHasKey('file', $data);
        $this->assertArrayHasKey('format', $data);
    }
}
