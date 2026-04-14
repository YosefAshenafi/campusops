<?php

declare(strict_types=1);

namespace tests\services;

use app\model\ActivityGroup;
use app\model\ActivityVersion;
use app\model\ActivitySignup;
use app\service\ActivityService;
use PHPUnit\Framework\TestCase;

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
    // listActivities
    // ------------------------------------------------------------------

    public function testListActivitiesReturnsOneRowPerGroupWhenMultipleVersionsExist(): void
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
        $v1->eligibility_tags   = json_encode([]);
        $v1->required_supplies  = json_encode([]);
        $v1->max_headcount = 0;
        $v1->save();

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

        $matchingRows = array_filter($result['list'], fn($item) => $item['id'] === $group->id);

        $this->assertCount(1, $matchingRows,
            'listActivities must return exactly one row per group_id, not one per version');
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

        $this->assertEquals('unit-test-activity-v2-latest', $row['title']);
        $this->assertEquals(2, $row['version_number']);
    }

    // ------------------------------------------------------------------
    // getActivity
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

        $this->assertTrue($result['has_pending_draft']);
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

    public function testGetActivityThrows404WhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        $this->service->getActivity(999999);
    }

    // ------------------------------------------------------------------
    // createActivity
    // ------------------------------------------------------------------

    public function testCreateActivityReturnsActivityWithDraftState(): void
    {
        $user = $this->mockUser();
        $result = $this->service->createActivity(['title' => 'unit-test-create'], $user);

        $this->assertEquals('unit-test-create', $result['title']);
        $this->assertEquals(ActivityService::STATE_DRAFT, $result['state']);
        $this->assertEquals(1, $result['version_number']);
    }

    public function testCreateActivityThrowsWhenTitleIsMissing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);

        $user = $this->mockUser();
        $this->service->createActivity([], $user);
    }

    // ------------------------------------------------------------------
    // updateActivity (draft) and createNewVersion (published)
    // ------------------------------------------------------------------

    public function testUpdateDraftActivityModifiesExistingVersion(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-original'], $user);

        $updated = $this->service->updateActivity($created['id'], ['title' => 'unit-test-updated'], $user);

        $this->assertEquals('unit-test-updated', $updated['title']);
        $this->assertEquals(1, $updated['version_number']);
    }

    public function testUpdatePublishedActivityCreatesNewVersion(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-publish-update'], $user);
        $this->service->publishActivity($created['id'], $user);

        $updated = $this->service->updateActivity($created['id'], ['title' => 'unit-test-v2-title'], $user);

        $this->assertEquals('unit-test-v2-title', $updated['title']);
        $this->assertEquals(2, $updated['version_number']);
        $this->assertEquals(ActivityService::STATE_DRAFT, $updated['state']);
    }

    // ------------------------------------------------------------------
    // State machine: publish, start, complete, archive
    // ------------------------------------------------------------------

    public function testPublishActivityTransitionsDraftToPublished(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-state-pub'], $user);

        $result = $this->service->publishActivity($created['id'], $user);

        $this->assertEquals(ActivityService::STATE_PUBLISHED, $result['state']);
    }

    public function testPublishThrowsWhenNotDraft(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-state-pub2'], $user);
        $this->service->publishActivity($created['id'], $user);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->publishActivity($created['id'], $user);
    }

    public function testStartActivityTransitionsPublishedToInProgress(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-state-start'], $user);
        $this->service->publishActivity($created['id'], $user);

        $result = $this->service->startActivity($created['id'], $user);

        $this->assertEquals(ActivityService::STATE_IN_PROGRESS, $result['state']);
    }

    public function testStartThrowsWhenNotPublished(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-state-start2'], $user);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->startActivity($created['id'], $user);
    }

    public function testCompleteActivityTransitionsInProgressToCompleted(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-state-complete'], $user);
        $this->service->publishActivity($created['id'], $user);
        $this->service->startActivity($created['id'], $user);

        $result = $this->service->completeActivity($created['id'], $user);

        $this->assertEquals(ActivityService::STATE_COMPLETED, $result['state']);
    }

    public function testArchiveActivityTransitionsCompletedToArchived(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-state-archive'], $user);
        $this->service->publishActivity($created['id'], $user);
        $this->service->startActivity($created['id'], $user);
        $this->service->completeActivity($created['id'], $user);

        $result = $this->service->archiveActivity($created['id'], $user);

        $this->assertEquals(ActivityService::STATE_ARCHIVED, $result['state']);
    }

    // ------------------------------------------------------------------
    // getVersions / getChangeLog
    // ------------------------------------------------------------------

    public function testGetVersionsReturnsAllVersions(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-versions'], $user);
        $this->service->publishActivity($created['id'], $user);
        $this->service->updateActivity($created['id'], ['title' => 'unit-test-versions-v2'], $user);

        $versions = $this->service->getVersions($created['id']);

        $this->assertCount(2, $versions);
        $this->assertEquals(2, $versions[0]['version_number']);
        $this->assertEquals(1, $versions[1]['version_number']);
    }

    public function testGetChangeLogRecordsChanges(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-changelog'], $user);
        $this->service->publishActivity($created['id'], $user);
        $this->service->updateActivity($created['id'], ['title' => 'unit-test-changelog-v2'], $user);

        $log = $this->service->getChangeLog($created['id']);

        $this->assertCount(1, $log);
        $this->assertEquals(1, $log[0]['from_version']);
        $this->assertEquals(2, $log[0]['to_version']);
    }

    // ------------------------------------------------------------------
    // signupUser / cancelSignup / acknowledgeChanges
    // ------------------------------------------------------------------

    public function testSignupUserSucceedsForPublishedActivity(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-signup'], $user);
        $this->service->publishActivity($created['id'], $user);

        $result = $this->service->signupUser($created['id'], $user);

        $this->assertEquals('confirmed', $result['status']);
        $this->assertEquals($user->id, $result['user_id']);
    }

    public function testSignupThrowsWhenActivityNotPublished(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-signup-draft'], $user);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->signupUser($created['id'], $user);
    }

    public function testSignupThrowsWhenAlreadySignedUp(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-signup-dup'], $user);
        $this->service->publishActivity($created['id'], $user);
        $this->service->signupUser($created['id'], $user);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->signupUser($created['id'], $user);
    }

    public function testSignupThrowsWhenActivityIsFull(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity([
            'title' => 'unit-test-signup-full',
            'max_headcount' => 1,
        ], $user);
        $this->service->publishActivity($created['id'], $user);
        $this->service->signupUser($created['id'], $user);

        $user2 = $this->mockUser(2);
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->service->signupUser($created['id'], $user2);
    }

    public function testCancelSignupSetsStatusToCancelled(): void
    {
        $user = $this->mockUser();
        $created = $this->service->createActivity(['title' => 'unit-test-cancel-signup'], $user);
        $this->service->publishActivity($created['id'], $user);
        $signup = $this->service->signupUser($created['id'], $user);

        $this->service->cancelSignup($created['id'], $signup['id'], $user);

        $s = ActivitySignup::find($signup['id']);
        $this->assertEquals('cancelled', $s->status);
    }

    public function testListActivitiesFiltersByState(): void
    {
        $user = $this->mockUser();
        $a1 = $this->service->createActivity(['title' => 'unit-test-filter-draft'], $user);
        $a2 = $this->service->createActivity(['title' => 'unit-test-filter-pub'], $user);
        $this->service->publishActivity($a2['id'], $user);

        $result = $this->service->listActivities(1, 20, 'published');
        $ids = array_column($result['list'], 'id');

        $this->assertContains($a2['id'], $ids);
        $this->assertNotContains($a1['id'], $ids);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function mockUser(int $id = 1): object
    {
        return new class($id) {
            public int $id;
            public string $role = 'administrator';
            public function __construct(int $id) { $this->id = $id; }
        };
    }

    private function cleanUp(): void
    {
        $testTitles = [
            'unit-test-activity-v1', 'unit-test-activity-v2', 'unit-test-activity-v2-latest',
            'unit-test-published', 'unit-test-draft-edit', 'unit-test-published-only',
            'unit-test-create', 'unit-test-original', 'unit-test-updated',
            'unit-test-publish-update', 'unit-test-v2-title',
            'unit-test-state-pub', 'unit-test-state-pub2',
            'unit-test-state-start', 'unit-test-state-start2',
            'unit-test-state-complete', 'unit-test-state-archive',
            'unit-test-versions', 'unit-test-versions-v2',
            'unit-test-changelog', 'unit-test-changelog-v2',
            'unit-test-signup', 'unit-test-signup-draft', 'unit-test-signup-dup',
            'unit-test-signup-full', 'unit-test-cancel-signup',
            'unit-test-filter-draft', 'unit-test-filter-pub',
        ];

        $groupIds = ActivityVersion::whereIn('title', $testTitles)->column('group_id');
        if (!empty($groupIds)) {
            ActivitySignup::whereIn('group_id', $groupIds)->delete();
            ActivityVersion::whereIn('group_id', $groupIds)->delete();
            ActivityGroup::whereIn('id', $groupIds)->delete();
        }
    }
}
