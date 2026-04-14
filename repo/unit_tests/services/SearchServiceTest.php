<?php

declare(strict_types=1);

namespace tests\services;

use app\model\SearchIndex;
use app\service\SearchService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SearchService — SQL safety and cleanup behaviour.
 */
class SearchServiceTest extends TestCase
{
    private SearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SearchService();
    }

    protected function tearDown(): void
    {
        // Remove any stale entries planted by the cleanup test
        SearchIndex::where('entity_id', 99999)->delete();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // SQL injection safety
    // ------------------------------------------------------------------

    public function testSearchWithSqlInjectionQueryReturnsValidArray(): void
    {
        $result = $this->service->search("' OR '1'='1", '', 1, 20, 'relevance');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testSearchWithDropTablePayloadReturnsValidArray(): void
    {
        $result = $this->service->search("%; DROP TABLE search_index; --", '', 1, 20, 'relevance');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);
    }

    public function testSearchWithWildcardPayloadDoesNotThrow(): void
    {
        $result = $this->service->search("%_%_%", '', 1, 20, 'relevance');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);
    }

    public function testSearchWithSingleQuoteInQueryDoesNotThrow(): void
    {
        $result = $this->service->search("O'Brien", '', 1, 20, 'relevance');

        $this->assertIsArray($result);
    }

    // ------------------------------------------------------------------
    // Cleanup
    // ------------------------------------------------------------------

    public function testCleanupRemovesOrphanedIndexEntriesOlderThan7Days(): void
    {
        // Insert a stale orphan: entity_id 99999 matches no real activity
        $stale = new SearchIndex();
        $stale->entity_type     = 'activity';
        $stale->entity_id       = 99999;
        $stale->title           = 'Ghost Activity';
        $stale->body            = '';
        $stale->tags            = json_encode([]);
        $stale->normalized_text = '';
        $stale->pinyin_text     = '';
        // created_at 8 days ago so it falls past the 7-day threshold
        $stale->created_at = date('Y-m-d H:i:s', strtotime('-8 days'));
        $stale->save();

        $removed = $this->service->cleanup();

        $this->assertGreaterThanOrEqual(1, $removed, 'At least the planted stale row should have been removed');
        $this->assertNull(SearchIndex::where('entity_id', 99999)->find(), 'Stale row must be deleted');
    }

    public function testCleanupDoesNotRemoveRecentOrphanedEntries(): void
    {
        // Entry created today — should NOT be cleaned up
        $recent = new SearchIndex();
        $recent->entity_type     = 'activity';
        $recent->entity_id       = 99999;
        $recent->title           = 'Ghost Activity Recent';
        $recent->body            = '';
        $recent->tags            = json_encode([]);
        $recent->normalized_text = '';
        $recent->pinyin_text     = '';
        $recent->created_at = date('Y-m-d H:i:s');
        $recent->save();

        $this->service->cleanup();

        $this->assertNotNull(
            SearchIndex::where('entity_id', 99999)->find(),
            'Recent orphan should not be removed by cleanup'
        );

        // Clean up manually so tearDown finds nothing
        SearchIndex::where('entity_id', 99999)->delete();
    }
}
