<?php

declare(strict_types=1);

namespace tests\services;

use app\model\Dashboard;
use app\service\DashboardService;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    private DashboardService $service;
    private int $testUserId = 99999;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardService();
        Dashboard::where('user_id', $this->testUserId)->delete();
    }

    protected function tearDown(): void
    {
        Dashboard::where('user_id', $this->testUserId)->delete();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // getCustom / saveCustom / deleteCustom contract
    // ------------------------------------------------------------------

    public function testGetCustomReturnsLayoutFieldWhenEmpty(): void
    {
        $result = $this->service->getCustom($this->testUserId);
        $this->assertArrayHasKey('layout', $result);
        $this->assertNull($result['layout']);
    }

    public function testSaveCustomWithLayoutFieldPersistsCorrectly(): void
    {
        $layout = ['orders_by_state', 'activities_by_state', 'recent_orders'];
        $result = $this->service->saveCustom($this->testUserId, ['layout' => $layout]);

        $this->assertArrayHasKey('layout', $result);
        $this->assertEquals($layout, $result['layout']);
    }

    public function testGetCustomReturnsLayoutAfterSave(): void
    {
        $layout = ['recent_orders', 'orders_by_state'];
        $this->service->saveCustom($this->testUserId, ['layout' => $layout]);

        $result = $this->service->getCustom($this->testUserId);
        $this->assertArrayHasKey('layout', $result);
        $this->assertEquals($layout, $result['layout']);
    }

    public function testSaveCustomWithNameFieldWorks(): void
    {
        $result = $this->service->saveCustom($this->testUserId, [
            'name' => 'my-dashboard',
            'widgets' => ['widget1', 'widget2'],
        ]);

        $this->assertEquals('my-dashboard', $result['name']);
    }

    public function testSaveCustomUpsertsExistingDashboard(): void
    {
        $this->service->saveCustom($this->testUserId, ['layout' => ['a']]);
        $this->service->saveCustom($this->testUserId, ['layout' => ['b', 'c']]);

        $count = Dashboard::where('user_id', $this->testUserId)->count();
        $this->assertEquals(1, $count);

        $result = $this->service->getCustom($this->testUserId);
        $this->assertEquals(['b', 'c'], $result['layout']);
    }

    public function testDeleteCustomRemovesDashboard(): void
    {
        $this->service->saveCustom($this->testUserId, ['layout' => ['a']]);
        $this->service->deleteCustom($this->testUserId);

        $result = $this->service->getCustom($this->testUserId);
        $this->assertNull($result['layout']);
    }

    // ------------------------------------------------------------------
    // getDefault
    // ------------------------------------------------------------------

    public function testGetDefaultReturnsWidgetsStructure(): void
    {
        $result = $this->service->getDefault($this->testUserId);
        $this->assertArrayHasKey('widgets', $result);
        $this->assertArrayHasKey('orders_by_state', $result['widgets']);
        $this->assertArrayHasKey('activities_by_state', $result['widgets']);
        $this->assertArrayHasKey('recent_orders', $result['widgets']);
    }
}
