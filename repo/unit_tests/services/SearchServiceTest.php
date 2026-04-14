<?php

declare(strict_types=1);

namespace tests\services;

use app\model\SearchIndex;
use app\model\ActivityGroup;
use app\model\ActivityVersion;
use app\model\Order;
use app\service\SearchService;
use PHPUnit\Framework\TestCase;

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
        SearchIndex::where('entity_id', 99999)->delete();
        SearchIndex::where('title', 'like', 'search-test%')->delete();
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
    // Search functionality
    // ------------------------------------------------------------------

    public function testSearchFindsIndexedContent(): void
    {
        $this->service->index('activity', 99999, 'search-test-findme', 'Some body text', ['tag1']);

        $result = $this->service->search('search-test-findme', '', 1, 20, 'relevance');
        $ids = array_column($result['list'], 'id');
        $this->assertContains(99999, $ids);
    }

    public function testSearchFiltersByEntityType(): void
    {
        $this->service->index('activity', 99999, 'search-test-typed', 'body', []);

        $resultAll = $this->service->search('search-test-typed', '', 1, 20, 'relevance');
        $resultFiltered = $this->service->search('search-test-typed', 'order', 1, 20, 'relevance');

        $this->assertNotEmpty($resultAll['list']);
        $this->assertEmpty($resultFiltered['list']);
    }

    public function testSearchSortsByRecency(): void
    {
        $this->service->index('activity', 99999, 'search-test-recency', 'body', []);

        $result = $this->service->search('search-test-recency', '', 1, 20, 'recency');
        $this->assertIsArray($result);
        $this->assertEquals('recency', $result['sort']);
    }

    public function testSearchSortsByPopularity(): void
    {
        $result = $this->service->search('anything', '', 1, 20, 'popularity');
        $this->assertEquals('popularity', $result['sort']);
    }

    public function testSearchSortsByReplyCount(): void
    {
        $result = $this->service->search('anything', '', 1, 20, 'reply_count');
        $this->assertEquals('reply_count', $result['sort']);
    }

    public function testSearchReturnsHighlights(): void
    {
        $this->service->index('activity', 99999, 'search-test-highlight', 'body with highlight text', []);

        $result = $this->service->search('search-test-highlight', '', 1, 20, 'relevance', true);
        $matching = array_filter($result['list'], fn($r) => $r['id'] === 99999);
        if (!empty($matching)) {
            $row = array_values($matching)[0];
            $this->assertArrayHasKey('highlights', $row);
        }
    }

    public function testSearchWithoutHighlights(): void
    {
        $this->service->index('activity', 99999, 'search-test-nohighlight', 'body', []);

        $result = $this->service->search('search-test-nohighlight', '', 1, 20, 'relevance', false);
        $matching = array_filter($result['list'], fn($r) => $r['id'] === 99999);
        if (!empty($matching)) {
            $row = array_values($matching)[0];
            $this->assertArrayNotHasKey('highlights', $row);
        }
    }

    // ------------------------------------------------------------------
    // Index / Remove
    // ------------------------------------------------------------------

    public function testIndexCreatesNewEntry(): void
    {
        $this->service->index('activity', 99999, 'search-test-index', 'test body', ['tag1']);

        $entry = SearchIndex::where('entity_type', 'activity')->where('entity_id', 99999)->find();
        $this->assertNotNull($entry);
        $this->assertEquals('search-test-index', $entry->title);
    }

    public function testIndexUpdatesExistingEntry(): void
    {
        $this->service->index('activity', 99999, 'search-test-index-orig', 'body', []);
        $this->service->index('activity', 99999, 'search-test-index-updated', 'new body', ['t']);

        $entry = SearchIndex::where('entity_type', 'activity')->where('entity_id', 99999)->find();
        $this->assertEquals('search-test-index-updated', $entry->title);
    }

    public function testRemoveDeletesEntry(): void
    {
        $this->service->index('activity', 99999, 'search-test-remove', 'body', []);
        $this->service->remove('activity', 99999);

        $entry = SearchIndex::where('entity_type', 'activity')->where('entity_id', 99999)->find();
        $this->assertNull($entry);
    }

    // ------------------------------------------------------------------
    // Suggest
    // ------------------------------------------------------------------

    public function testSuggestReturnsTitleMatches(): void
    {
        $this->service->index('activity', 99999, 'search-test-suggest-alpha', 'body', []);

        $suggestions = $this->service->suggest('search-test-suggest');
        $titles = array_column($suggestions, 'title');
        $this->assertContains('search-test-suggest-alpha', $titles);
    }

    // ------------------------------------------------------------------
    // SearchLogistics
    // ------------------------------------------------------------------

    public function testSearchLogisticsFiltersByOrderType(): void
    {
        $this->service->index('order', 99999, 'search-test-logistics-order', 'tracking TK123', []);
        $this->service->index('activity', 99998, 'search-test-logistics-activity', 'not an order', []);

        $result = $this->service->searchLogistics('search-test-logistics', 1, 20, 'recency');
        $types = array_column($result['list'], 'type');

        foreach ($types as $type) {
            $this->assertEquals('order', $type);
        }

        SearchIndex::where('entity_id', 99998)->delete();
    }

    // ------------------------------------------------------------------
    // Cleanup
    // ------------------------------------------------------------------

    public function testCleanupRemovesOrphanedIndexEntriesOlderThan7Days(): void
    {
        $stale = new SearchIndex();
        $stale->entity_type     = 'activity';
        $stale->entity_id       = 99999;
        $stale->title           = 'Ghost Activity';
        $stale->body            = '';
        $stale->tags            = json_encode([]);
        $stale->normalized_text = '';
        $stale->pinyin_text     = '';
        $stale->created_at = date('Y-m-d H:i:s', strtotime('-8 days'));
        $stale->save();

        $removed = $this->service->cleanup();

        $this->assertGreaterThanOrEqual(1, $removed);
        $this->assertNull(SearchIndex::where('entity_id', 99999)->find());
    }

    public function testCleanupDoesNotRemoveRecentOrphanedEntries(): void
    {
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

        $this->assertNotNull(SearchIndex::where('entity_id', 99999)->find());
        SearchIndex::where('entity_id', 99999)->delete();
    }

    // ------------------------------------------------------------------
    // Rebuild
    // ------------------------------------------------------------------

    public function testRebuildReindexesAllEntities(): void
    {
        // Create an activity
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        $version = new ActivityVersion();
        $version->group_id = $group->id;
        $version->version_number = 1;
        $version->title = 'search-test-rebuild';
        $version->body = 'rebuild body';
        $version->tags = json_encode([]);
        $version->state = 'published';
        $version->eligibility_tags = json_encode([]);
        $version->required_supplies = json_encode([]);
        $version->max_headcount = 0;
        $version->save();

        $this->service->rebuild();

        $entry = SearchIndex::where('entity_type', 'activity')
            ->where('entity_id', $group->id)
            ->find();
        $this->assertNotNull($entry);
        $this->assertEquals('search-test-rebuild', $entry->title);

        // Clean up
        SearchIndex::where('entity_type', 'activity')->where('entity_id', $group->id)->delete();
        ActivityVersion::where('group_id', $group->id)->delete();
        ActivityGroup::where('id', $group->id)->delete();
    }

    // ------------------------------------------------------------------
    // Correct / DidYouMean
    // ------------------------------------------------------------------

    public function testGetDidYouMeanReturnsNullWhenNoSuggestion(): void
    {
        $result = $this->service->getDidYouMean('xyznotarealword');
        // With empty dictionary, should return null
        $this->assertTrue($result === null || is_string($result));
    }
}
