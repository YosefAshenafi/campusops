<?php

declare(strict_types=1);

namespace tests\api;

/**
 * HTTP endpoint tests for search sub-routes, index management, and export sub-routes:
 *
 * Search:
 *   GET /api/v1/search/suggest
 *   GET /api/v1/search/logistics
 *
 * Index management:
 *   GET  /api/v1/index/status
 *   POST /api/v1/index/rebuild
 *   POST /api/v1/index/cleanup
 *
 * Export:
 *   GET /api/v1/export/activities
 *   GET /api/v1/export/violations
 *   GET /api/v1/export/download
 */
class EndpointSearchIndexExportTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->token = '';
        $this->cleanupUsersLike('http-sie-%');
    }

    protected function tearDown(): void
    {
        $this->token = '';
        $this->cleanupUsersLike('http-sie-%');
        parent::tearDown();
    }

    private function loginAdmin(): void
    {
        $this->loginAsRole('administrator', 'http-sie-admin');
    }

    private function loginRegular(): void
    {
        $this->loginAsRole('regular_user', 'http-sie-regular');
    }

    // ======================================================================
    // GET /api/v1/search/suggest
    // ======================================================================

    public function testSearchSuggestReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/search/suggest?q=test');
        $this->assertUnauthorized($res);
    }

    public function testSearchSuggestReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/search/suggest?q=test');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testSearchSuggestReturns200ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->get('/api/v1/search/suggest?q=test');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // GET /api/v1/search/logistics
    // ======================================================================

    public function testSearchLogisticsReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/search/logistics?q=test');
        $this->assertUnauthorized($res);
    }

    public function testSearchLogisticsReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/search/logistics?q=test');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testSearchLogisticsReturns200ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->get('/api/v1/search/logistics?q=test');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // GET /api/v1/index/status
    // ======================================================================

    public function testIndexStatusReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/index/status');
        $this->assertUnauthorized($res);
    }

    public function testIndexStatusReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->get('/api/v1/index/status');
        $this->assertForbidden($res);
    }

    public function testIndexStatusReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/index/status');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // POST /api/v1/index/rebuild
    // ======================================================================

    public function testIndexRebuildReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/index/rebuild');
        $this->assertUnauthorized($res);
    }

    public function testIndexRebuildReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->post('/api/v1/index/rebuild');
        $this->assertForbidden($res);
    }

    public function testIndexRebuildReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->post('/api/v1/index/rebuild');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // POST /api/v1/index/cleanup
    // ======================================================================

    public function testIndexCleanupReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/index/cleanup');
        $this->assertUnauthorized($res);
    }

    public function testIndexCleanupReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->post('/api/v1/index/cleanup');
        $this->assertForbidden($res);
    }

    public function testIndexCleanupReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->post('/api/v1/index/cleanup');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // GET /api/v1/export/activities
    // ======================================================================

    public function testExportActivitiesReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/export/activities');
        $this->assertUnauthorized($res);
    }

    public function testExportActivitiesReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->get('/api/v1/export/activities');
        $this->assertForbidden($res);
    }

    public function testExportActivitiesReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/export/activities');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // GET /api/v1/export/violations
    // ======================================================================

    public function testExportViolationsReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/export/violations');
        $this->assertUnauthorized($res);
    }

    public function testExportViolationsReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->get('/api/v1/export/violations');
        $this->assertForbidden($res);
    }

    public function testExportViolationsReturns200ForAdmin(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/export/violations');
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ======================================================================
    // GET /api/v1/export/download
    // ======================================================================

    public function testExportDownloadReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/export/download?file=test.csv');
        $this->assertUnauthorized($res);
    }

    public function testExportDownloadReturns403ForRegularUser(): void
    {
        $this->loginRegular();
        $res = $this->get('/api/v1/export/download?file=test.csv');
        $this->assertForbidden($res);
    }

    public function testExportDownloadReturns404ForMissingFile(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/export/download?file=nonexistent_file_xyz.csv');
        // Should return 404 when the requested export file does not exist.
        $this->assertNotFound($res);
    }

    public function testExportDownloadReturns400WhenFileParamMissing(): void
    {
        $this->loginAdmin();
        $res = $this->get('/api/v1/export/download');
        // Missing file param triggers a 400 or 404 error.
        $this->assertTrue(
            $res['status'] === 400 || $res['status'] === 404,
            "Expected 400 or 404, got {$res['status']}"
        );
    }
}
