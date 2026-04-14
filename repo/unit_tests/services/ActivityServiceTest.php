<?php

declare(strict_types=1);

namespace tests\services;

use app\model\ActivityGroup;
use app\model\ActivityVersion;
use app\service\ActivityService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ActivityService list de-duplication and canonical detail view.
 */
class ActivityServiceTest extends TestCase
{
    private ActivityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ActivityService();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // listActivities — one row per group_id
    // ------------------------------------------------------------------

    public function testListActivitiesReturnsOneRowPerGroupWhenMultipleVersionsExist(): void
    {
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        // Version 1 — published
        $v1 = new ActivityVersion();
        $v1->group_id      = $group->id;
        $v1->version_number = 1;
        $v1->title         = 'unit-test-activity-v1';
        $v1->body          = '';
        $v1->tags          = json_encode([]);
        $v1->state         = ActivityService::STATE_PUBLISHED;
        $v1->eligibility_tags   = json_encode([]);
        $v1->required_supplies  = json_encode([]);
        $v1->max_headcount = 0;
        $v1->save();

        // Version 2 — draft (edit of published)
        $v2 = new ActivityVersion();
        $v2->group_id      = $group->id;
        $v2->version_number = 2;
        $v2->title         = 'unit-test-activity-v2';
        $v2->body          = '';
        $v2->tags          = json_encode([]);
        $v2->state         = ActivityService::STATE_DRAFT;
        $v2->eligibility_tags   = json_encode([]);
        $v2->required_supplies  = json_encode([]);
        $v2->max_headcount = 0;
        $v2->save();

        $result = $this->service->listActivities();

        // Count how many entries in the list belong to this group
        $matchingRows = array_filter($result['list'], fn($item) => $item['id'] === $group->id);

        $this->assertCount(
            1,
            $matchingRows,
            'listActivities must return exactly one row per group_id, not one per version'
        );
    }

    public function testListActivitiesLatestVersionIsShown(): void
    {
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        $v1 = new ActivityVersion();
        $v1->group_id      = $group->id;
        $v1->version_number = 1;
        $v1->title         = 'unit-test-activity-v1';
        $v1->body          = '';
        $v1->tags          = json_encode([]);
        $v1->state         = ActivityService::STATE_PUBLISHED;
        $v1->eligibility_tags  = json_encode([]);
        $v1->required_supplies = json_encode([]);
        $v1->max_headcount = 0;
        $v1->save();

        $v2 = new ActivityVersion();
        $v2->group_id      = $group->id;
        $v2->version_number = 2;
        $v2->title         = 'unit-test-activity-v2-latest';
        $v2->body          = '';
        $v2->tags          = json_encode([]);
        $v2->state         = ActivityService::STATE_DRAFT;
        $v2->eligibility_tags  = json_encode([]);
        $v2->required_supplies = json_encode([]);
        $v2->max_headcount = 0;
        $v2->save();

        $result = $this->service->listActivities();

        $matching = array_filter($result['list'], fn($item) => $item['id'] === $group->id);
        $row = array_values($matching)[0];

        $this->assertEquals(
            'unit-test-activity-v2-latest',
            $row['title'],
            'The listed row should show the latest version title'
        );
        $this->assertEquals(2, $row['version_number']);
    }

    // ------------------------------------------------------------------
    // getActivity — canonical published view + has_pending_draft flag
    // ------------------------------------------------------------------

    public function testGetActivityReturnsMostRecentPublishedVersion(): void
    {
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        $published = new ActivityVersion();
        $published->group_id      = $group->id;
        $published->version_number = 1;
        $published->title         = 'unit-test-published';
        $published->body          = '';
        $published->tags          = json_encode([]);
        $published->state         = ActivityService::STATE_PUBLISHED;
        $published->eligibility_tags  = json_encode([]);
        $published->required_supplies = json_encode([]);
        $published->max_headcount = 0;
        $published->save();

        $draft = new ActivityVersion();
        $draft->group_id      = $group->id;
        $draft->version_number = 2;
        $draft->title         = 'unit-test-draft-edit';
        $draft->body          = '';
        $draft->tags          = json_encode([]);
        $draft->state         = ActivityService::STATE_DRAFT;
        $draft->eligibility_tags  = json_encode([]);
        $draft->required_supplies = json_encode([]);
        $draft->max_headcount = 0;
        $draft->save();

        $result = $this->service->getActivity($group->id);

        // Canonical view should be the published version, not the draft
        $this->assertEquals('unit-test-published', $result['title']);
        $this->assertEquals(ActivityService::STATE_PUBLISHED, $result['state']);
    }

    public function testGetActivitySetsPendingDraftFlagWhenNewerDraftExists(): void
    {
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        $published = new ActivityVersion();
        $published->group_id      = $group->id;
        $published->version_number = 1;
        $published->title         = 'unit-test-published';
        $published->body          = '';
        $published->tags          = json_encode([]);
        $published->state         = ActivityService::STATE_PUBLISHED;
        $published->eligibility_tags  = json_encode([]);
        $published->required_supplies = json_encode([]);
        $published->max_headcount = 0;
        $published->save();

        $draft = new ActivityVersion();
        $draft->group_id      = $group->id;
        $draft->version_number = 2;
        $draft->title         = 'unit-test-draft-edit';
        $draft->body          = '';
        $draft->tags          = json_encode([]);
        $draft->state         = ActivityService::STATE_DRAFT;
        $draft->eligibility_tags  = json_encode([]);
        $draft->required_supplies = json_encode([]);
        $draft->max_headcount = 0;
        $draft->save();

        $result = $this->service->getActivity($group->id);

        $this->assertTrue($result['has_pending_draft'], 'has_pending_draft should be true when a newer draft exists');
    }

    public function testGetActivityHasPendingDraftIsFalseWhenNoDraftExists(): void
    {
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        $published = new ActivityVersion();
        $published->group_id      = $group->id;
        $published->version_number = 1;
        $published->title         = 'unit-test-published-only';
        $published->body          = '';
        $published->tags          = json_encode([]);
        $published->state         = ActivityService::STATE_PUBLISHED;
        $published->eligibility_tags  = json_encode([]);
        $published->required_supplies = json_encode([]);
        $published->max_headcount = 0;
        $published->save();

        $result = $this->service->getActivity($group->id);

        $this->assertFalse($result['has_pending_draft']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function cleanUp(): void
    {
        // Find groups whose latest version has our test titles
        $testTitles = [
            'unit-test-activity-v1',
            'unit-test-activity-v2',
            'unit-test-activity-v2-latest',
            'unit-test-published',
            'unit-test-draft-edit',
            'unit-test-published-only',
        ];

        $groupIds = ActivityVersion::whereIn('title', $testTitles)->column('group_id');
        if (!empty($groupIds)) {
            ActivityVersion::whereIn('group_id', $groupIds)->delete();
            ActivityGroup::whereIn('id', $groupIds)->delete();
        }
    }
}
