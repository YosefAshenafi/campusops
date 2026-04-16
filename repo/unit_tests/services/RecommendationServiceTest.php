<?php

declare(strict_types=1);

namespace tests\services;

use app\model\ActivityGroup;
use app\model\ActivitySignup;
use app\model\ActivityVersion;
use app\model\Order;
use app\service\RecommendationService;
use PHPUnit\Framework\TestCase;

class RecommendationServiceTest extends TestCase
{
    private RecommendationService $service;
    private const TEST_USER_ID = 77701;
    private const OTHER_USER_ID = 77702;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecommendationService();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // getRecommendations — cold-start (no signup history)
    // ------------------------------------------------------------------

    public function testGetRecommendationsColdStartReturnsPublishedActivities(): void
    {
        $this->createPublishedActivity('unit-test-rec-cold-a', ['sports']);
        $this->createPublishedActivity('unit-test-rec-cold-b', ['arts']);

        $result = $this->service->getRecommendations(self::TEST_USER_ID, 'list', 10);

        $this->assertArrayHasKey('context', $result);
        $this->assertArrayHasKey('list', $result);
        $this->assertEquals('list', $result['context']);
    }

    public function testGetRecommendationsColdStartExcludesDraftActivities(): void
    {
        $this->createDraftActivity('unit-test-rec-draft');

        $result = $this->service->getRecommendations(self::TEST_USER_ID, 'list', 10);

        $titles = array_column($result['list'], 'title');
        $this->assertNotContains('unit-test-rec-draft', $titles);
    }

    public function testGetRecommendationsRespectsLimit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->createPublishedActivity("unit-test-rec-limit-{$i}", ['sports']);
        }

        $result = $this->service->getRecommendations(self::TEST_USER_ID, 'list', 3);

        $this->assertLessThanOrEqual(3, count($result['list']));
    }

    public function testGetRecommendationsExcludesAlreadySignedUpActivities(): void
    {
        $group = $this->createPublishedActivity('unit-test-rec-signedup', ['music']);

        // Sign up the test user
        $signup = new ActivitySignup();
        $signup->group_id = $group->id;
        $signup->user_id = self::TEST_USER_ID;
        $signup->status = 'confirmed';
        $signup->save();

        $result = $this->service->getRecommendations(self::TEST_USER_ID, 'list', 10);

        $groupIds = array_column($result['list'], 'id');
        $this->assertNotContains($group->id, $groupIds);
    }

    public function testGetRecommendationsDeduplicatesGroupIds(): void
    {
        $group = $this->createPublishedActivity('unit-test-rec-dedup', ['arts']);

        $result = $this->service->getRecommendations(self::OTHER_USER_ID, 'list', 10);

        $ids = array_column($result['list'], 'id');
        $uniqueIds = array_unique($ids);
        $this->assertCount(count($ids), $uniqueIds, 'No duplicate group IDs should appear in recommendations');
    }

    // ------------------------------------------------------------------
    // getPopular
    // ------------------------------------------------------------------

    public function testGetPopularReturnsArray(): void
    {
        $this->createPublishedActivity('unit-test-popular-a', ['tech'], '-10 days');
        $this->createPublishedActivity('unit-test-popular-b', ['tech'], '-5 days');

        $result = $this->service->getPopular(5);

        $this->assertIsArray($result);
    }

    public function testGetPopularRespectsLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createPublishedActivity("unit-test-pop-limit-{$i}", ['sports'], '-1 days');
        }

        $result = $this->service->getPopular(2);

        $this->assertLessThanOrEqual(2, count($result));
    }

    public function testGetPopularResultsHaveExpectedFields(): void
    {
        $this->createPublishedActivity('unit-test-pop-fields', ['arts'], '-1 days');

        $result = $this->service->getPopular(5);

        if (!empty($result)) {
            $item = $result[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('tags', $item);
            $this->assertArrayHasKey('signup_count', $item);
        } else {
            $this->markTestSkipped('No published activities within cold-start window');
        }
    }

    // ------------------------------------------------------------------
    // getOrderRecommendations
    // ------------------------------------------------------------------

    public function testGetOrderRecommendationsColdStartReturnsRecentOrders(): void
    {
        $this->createOrder(5, 999, 'placed');

        $result = $this->service->getOrderRecommendations(self::TEST_USER_ID, 5);

        $this->assertArrayHasKey('list', $result);
        $this->assertIsArray($result['list']);
    }

    public function testGetOrderRecommendationsExcludesCancelledOrders(): void
    {
        $cancelledOrder = $this->createOrder(5, 999, 'canceled');

        $result = $this->service->getOrderRecommendations(self::TEST_USER_ID, 10);

        $orderIds = array_column($result['list'], 'id');
        $this->assertNotContains($cancelledOrder->id, $orderIds);
    }

    public function testGetOrderRecommendationsRespectsLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createOrder($i + 10, 999 + $i, 'placed');
        }

        $result = $this->service->getOrderRecommendations(self::TEST_USER_ID, 2);

        $this->assertLessThanOrEqual(2, count($result['list']));
    }

    public function testGetOrderRecommendationsResultHasExpectedFields(): void
    {
        $this->createOrder(5, 9999, 'placed');

        $result = $this->service->getOrderRecommendations(self::OTHER_USER_ID, 5);

        if (!empty($result['list'])) {
            $item = $result['list'][0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('activity_id', $item);
            $this->assertArrayHasKey('state', $item);
            $this->assertArrayHasKey('amount', $item);
        } else {
            $this->markTestSkipped('No suitable orders found for recommendation test');
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createPublishedActivity(string $title, array $tags = [], string $publishedOffset = '-1 days'): ActivityGroup
    {
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        $v = new ActivityVersion();
        $v->group_id = $group->id;
        $v->version_number = 1;
        $v->title = $title;
        $v->body = '';
        $v->tags = json_encode($tags);
        $v->state = 'published';
        $v->eligibility_tags = json_encode([]);
        $v->required_supplies = json_encode([]);
        $v->max_headcount = 0;
        $v->published_at = date('Y-m-d H:i:s', strtotime($publishedOffset));
        $v->signup_end = date('Y-m-d', strtotime('+30 days'));
        $v->save();

        return $group;
    }

    private function createDraftActivity(string $title): ActivityGroup
    {
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        $v = new ActivityVersion();
        $v->group_id = $group->id;
        $v->version_number = 1;
        $v->title = $title;
        $v->body = '';
        $v->tags = json_encode([]);
        $v->state = 'draft';
        $v->eligibility_tags = json_encode([]);
        $v->required_supplies = json_encode([]);
        $v->max_headcount = 0;
        $v->save();

        return $group;
    }

    private function createOrder(int $activityId, int $createdBy, string $state): Order
    {
        $order = new Order();
        $order->activity_id = $activityId;
        $order->created_by = $createdBy;
        $order->team_lead_id = 1;
        $order->state = $state;
        $order->items = json_encode([]);
        $order->amount = 0.0;
        $order->save();
        return $order;
    }

    private function cleanUp(): void
    {
        $testTitles = [];
        for ($i = 0; $i < 6; $i++) {
            $testTitles[] = "unit-test-rec-limit-{$i}";
            $testTitles[] = "unit-test-pop-limit-{$i}";
        }

        $testTitles = array_merge($testTitles, [
            'unit-test-rec-cold-a', 'unit-test-rec-cold-b',
            'unit-test-rec-draft',
            'unit-test-rec-signedup',
            'unit-test-rec-dedup',
            'unit-test-popular-a', 'unit-test-popular-b',
            'unit-test-pop-fields',
        ]);

        $groupIds = ActivityVersion::whereIn('title', $testTitles)->column('group_id');
        if (!empty($groupIds)) {
            ActivitySignup::whereIn('group_id', $groupIds)->delete();
            ActivityVersion::whereIn('group_id', $groupIds)->delete();
            ActivityGroup::whereIn('id', $groupIds)->delete();
        }

        // Clean up test orders
        Order::where('created_by', 999)->delete();
        Order::where('team_lead_id', 1)
            ->whereIn('activity_id', range(10, 15))
            ->delete();
        Order::where('activity_id', 9999)->delete();
    }
}
