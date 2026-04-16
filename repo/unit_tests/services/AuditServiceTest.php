<?php

declare(strict_types=1);

namespace tests\services;

use app\model\AuditTrail;
use app\service\AuditService;
use PHPUnit\Framework\TestCase;

class AuditServiceTest extends TestCase
{
    private AuditService $service;
    private const ENTITY_TYPE = 'unit_test_entity';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuditService();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // log
    // ------------------------------------------------------------------

    public function testLogCreatesAuditEntry(): void
    {
        $this->service->log(1, self::ENTITY_TYPE, 42, 'unit_test_create');

        $entry = AuditTrail::where('entity_type', self::ENTITY_TYPE)
            ->where('entity_id', 42)
            ->where('action', 'unit_test_create')
            ->find();

        $this->assertNotNull($entry);
        $this->assertEquals(1, $entry->user_id);
        $this->assertEquals(42, $entry->entity_id);
        $this->assertEquals('unit_test_create', $entry->action);
    }

    public function testLogRecordsStateTransition(): void
    {
        $this->service->log(1, self::ENTITY_TYPE, 43, 'unit_test_transition', 'draft', 'published');

        $entry = AuditTrail::where('entity_type', self::ENTITY_TYPE)
            ->where('entity_id', 43)
            ->find();

        $this->assertEquals('draft', $entry->old_state);
        $this->assertEquals('published', $entry->new_state);
    }

    public function testLogStoresMetadataAsJson(): void
    {
        $meta = ['reason' => 'unit test', 'ip' => '127.0.0.1'];
        $this->service->log(1, self::ENTITY_TYPE, 44, 'unit_test_meta', '', '', $meta);

        $entry = AuditTrail::where('entity_type', self::ENTITY_TYPE)
            ->where('entity_id', 44)
            ->find();

        $decoded = json_decode($entry->metadata, true);
        $this->assertEquals('unit test', $decoded['reason']);
        $this->assertEquals('127.0.0.1', $decoded['ip']);
    }

    // ------------------------------------------------------------------
    // query
    // ------------------------------------------------------------------

    public function testQueryReturnsAllEntries(): void
    {
        $this->service->log(1, self::ENTITY_TYPE, 50, 'unit_test_q1');
        $this->service->log(2, self::ENTITY_TYPE, 51, 'unit_test_q2');

        $result = $this->service->query(1);

        $actions = array_column($result['list'], 'action');
        $this->assertContains('unit_test_q1', $actions);
        $this->assertContains('unit_test_q2', $actions);
    }

    public function testQueryFiltersByEntityType(): void
    {
        $this->service->log(1, self::ENTITY_TYPE, 60, 'unit_test_type_filter');
        $this->service->log(1, 'other_type', 61, 'unit_test_other_type_action');

        $result = $this->service->query(1, self::ENTITY_TYPE);

        foreach ($result['list'] as $entry) {
            $this->assertEquals(self::ENTITY_TYPE, $entry['entity_type']);
        }
    }

    public function testQueryFiltersByEntityId(): void
    {
        $this->service->log(1, self::ENTITY_TYPE, 70, 'unit_test_id_filter');
        $this->service->log(1, self::ENTITY_TYPE, 71, 'unit_test_id_other');

        $result = $this->service->query(1, self::ENTITY_TYPE, 70);

        $ids = array_column($result['list'], 'entity_id');
        $this->assertContains(70, $ids);
        $this->assertNotContains(71, $ids);
    }

    public function testQueryFiltersByAction(): void
    {
        $this->service->log(1, self::ENTITY_TYPE, 80, 'unit_test_specific_action');
        $this->service->log(1, self::ENTITY_TYPE, 81, 'unit_test_other_action');

        $result = $this->service->query(1, '', 0, 'unit_test_specific_action');

        foreach ($result['list'] as $entry) {
            $this->assertEquals('unit_test_specific_action', $entry['action']);
        }
    }

    public function testQueryReturnsPaginationMetadata(): void
    {
        $this->service->log(1, self::ENTITY_TYPE, 90, 'unit_test_page');

        $result = $this->service->query(1, self::ENTITY_TYPE);

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('limit', $result);
    }

    public function testQueryResultContainsExpectedFields(): void
    {
        $this->service->log(1, self::ENTITY_TYPE, 95, 'unit_test_fields', 'old', 'new', ['k' => 'v']);

        $result = $this->service->query(1, self::ENTITY_TYPE, 95);

        $this->assertNotEmpty($result['list']);
        $entry = $result['list'][0];
        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('user_id', $entry);
        $this->assertArrayHasKey('entity_type', $entry);
        $this->assertArrayHasKey('entity_id', $entry);
        $this->assertArrayHasKey('action', $entry);
        $this->assertArrayHasKey('old_state', $entry);
        $this->assertArrayHasKey('new_state', $entry);
        $this->assertArrayHasKey('metadata', $entry);
        $this->assertEquals(['k' => 'v'], $entry['metadata']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function cleanUp(): void
    {
        AuditTrail::where('entity_type', self::ENTITY_TYPE)->delete();
        AuditTrail::where('entity_type', 'other_type')
            ->where('action', 'like', 'unit_test%')
            ->delete();
        AuditTrail::where('action', 'like', 'unit_test%')->delete();
    }
}
