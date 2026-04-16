<?php

declare(strict_types=1);

namespace tests\api;

use app\model\Notification;
use app\model\UserPreference;

/**
 * HTTP endpoint tests for miscellaneous endpoint groups:
 *
 * Notifications:
 *   GET /api/v1/notifications
 *   GET /api/v1/notifications/settings
 *   PUT /api/v1/notifications/settings
 *
 * Preferences:
 *   GET /api/v1/preferences
 *   PUT /api/v1/preferences
 *
 * Recommendations:
 *   GET /api/v1/recommendations
 *   GET /api/v1/recommendations/popular
 *   GET /api/v1/recommendations/orders
 *
 * Dashboard:
 *   GET /api/v1/dashboard
 *   GET /api/v1/dashboard/favorites
 *
 * Audit:
 *   GET /api/v1/audit
 *
 * Search:
 *   GET /api/v1/search
 *
 * Export:
 *   GET /api/v1/export/orders
 */
class EndpointMiscTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupUsersLike('http-misc-%');
        $this->cleanupUsersLike('http-test-admin%');
    }

    protected function tearDown(): void
    {
        $this->cleanupUsersLike('http-misc-%');
        $this->cleanupUsersLike('http-test-admin%');
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Notifications — GET /api/v1/notifications
    // ------------------------------------------------------------------

    public function testGetNotificationsReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/notifications');
        $this->assertUnauthorized($res);
    }

    public function testGetNotificationsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/notifications');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
        $this->assertArrayHasKey('list', $res['body']['data'] ?? []);
    }

    // ------------------------------------------------------------------
    // Notifications settings — GET /api/v1/notifications/settings
    // ------------------------------------------------------------------

    public function testGetNotificationSettingsReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/notifications/settings');
        $this->assertUnauthorized($res);
    }

    public function testGetNotificationSettingsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/notifications/settings');

        $this->assertStatus(200, $res);
        $data = $res['body']['data'] ?? [];
        $this->assertArrayHasKey('order_alerts', $data);
        $this->assertArrayHasKey('activity_alerts', $data);
    }

    // ------------------------------------------------------------------
    // Notifications settings — PUT /api/v1/notifications/settings
    // ------------------------------------------------------------------

    public function testUpdateNotificationSettingsReturns401WhenUnauthenticated(): void
    {
        $res = $this->put('/api/v1/notifications/settings', ['order_alerts' => false]);
        $this->assertUnauthorized($res);
    }

    public function testUpdateNotificationSettingsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->put('/api/v1/notifications/settings', [
            'order_alerts'    => false,
            'activity_alerts' => true,
        ]);

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // Preferences — GET /api/v1/preferences
    // ------------------------------------------------------------------

    public function testGetPreferencesReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/preferences');
        $this->assertUnauthorized($res);
    }

    public function testGetPreferencesReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/preferences');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // Preferences — PUT /api/v1/preferences
    // ------------------------------------------------------------------

    public function testUpdatePreferencesReturns401WhenUnauthenticated(): void
    {
        $res = $this->put('/api/v1/preferences', ['order_alerts' => false]);
        $this->assertUnauthorized($res);
    }

    public function testUpdatePreferencesReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->put('/api/v1/preferences', ['order_alerts' => false]);

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // Recommendations — GET /api/v1/recommendations
    // ------------------------------------------------------------------

    public function testGetRecommendationsReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/recommendations');
        $this->assertUnauthorized($res);
    }

    public function testGetRecommendationsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/recommendations');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // Recommendations popular — GET /api/v1/recommendations/popular
    // ------------------------------------------------------------------

    public function testGetPopularReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/recommendations/popular');
        $this->assertUnauthorized($res);
    }

    public function testGetPopularReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/recommendations/popular');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // Recommendations orders — GET /api/v1/recommendations/orders
    // ------------------------------------------------------------------

    public function testGetOrderRecommendationsReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/recommendations/orders');
        $this->assertUnauthorized($res);
    }

    public function testGetOrderRecommendationsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/recommendations/orders');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // Dashboard — GET /api/v1/dashboard
    // ------------------------------------------------------------------

    public function testGetDashboardReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/dashboard');
        $this->assertUnauthorized($res);
    }

    public function testGetDashboardReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/dashboard');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // Dashboard favorites — GET /api/v1/dashboard/favorites
    // ------------------------------------------------------------------

    public function testGetDashboardFavoritesReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/dashboard/favorites');
        $this->assertUnauthorized($res);
    }

    public function testGetDashboardFavoritesReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/dashboard/favorites');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // Audit — GET /api/v1/audit
    // ------------------------------------------------------------------

    public function testGetAuditReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/audit');
        $this->assertUnauthorized($res);
    }

    public function testGetAuditReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-misc-regular');
        $res = $this->get('/api/v1/audit');
        $this->assertForbidden($res);
    }

    public function testGetAuditReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/audit');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
        $data = $res['body']['data'] ?? [];
        $this->assertArrayHasKey('list', $data);
        $this->assertArrayHasKey('total', $data);
    }

    // ------------------------------------------------------------------
    // Search — GET /api/v1/search
    // ------------------------------------------------------------------

    public function testSearchReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/search?q=test');
        $this->assertUnauthorized($res);
    }

    public function testSearchReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/search?q=test');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // Export — GET /api/v1/export/orders
    // ------------------------------------------------------------------

    public function testExportOrdersReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/export/orders');
        $this->assertUnauthorized($res);
    }

    public function testExportOrdersReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-misc-regular');
        $res = $this->get('/api/v1/export/orders');
        $this->assertForbidden($res);
    }

    public function testExportOrdersReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/export/orders');

        // 200 success or 200 with a file redirect — status 200
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }
}
