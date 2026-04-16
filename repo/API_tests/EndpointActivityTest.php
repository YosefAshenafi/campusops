<?php

declare(strict_types=1);

namespace tests\api;

use app\model\ActivityGroup;
use app\model\ActivityVersion;
use app\model\ActivitySignup;

/**
 * HTTP endpoint tests for:
 *   GET    /api/v1/activities
 *   GET    /api/v1/activities/:id
 *   GET    /api/v1/activities/:id/versions
 *   GET    /api/v1/activities/:id/signups
 *   GET    /api/v1/activities/:id/change-log
 *   POST   /api/v1/activities
 *   PUT    /api/v1/activities/:id
 *   POST   /api/v1/activities/:id/publish
 *   POST   /api/v1/activities/:id/signups
 *   DELETE /api/v1/activities/:id/signups/:signup_id
 */
class EndpointActivityTest extends HttpTestCase
{
    private ActivityGroup $group;
    private int $versionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupUsersLike('http-act-%');
        $this->cleanupUsersLike('http-test-admin%');
        $this->cleanupTestActivities();

        // Seed a published activity for read tests
        $this->group   = $this->createPublishedActivity('http-act-seed-title');
        $this->versionId = ActivityVersion::where('group_id', $this->group->id)->value('id');
    }

    protected function tearDown(): void
    {
        $this->cleanupTestActivities();
        $this->cleanupUsersLike('http-act-%');
        $this->cleanupUsersLike('http-test-admin%');
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // GET /api/v1/activities
    // ------------------------------------------------------------------

    public function testListActivitiesReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/activities');
        $this->assertUnauthorized($res);
    }

    public function testListActivitiesReturns200WithAuthAndPermission(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/activities');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
        $this->assertArrayHasKey('list', $res['body']['data'] ?? []);
    }

    public function testListActivitiesResponseHasPaginationFields(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/activities');

        $data = $res['body']['data'] ?? [];
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/activities/:id
    // ------------------------------------------------------------------

    public function testGetActivityReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/activities/' . $this->group->id);
        $this->assertUnauthorized($res);
    }

    public function testGetActivityReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/activities/' . $this->group->id);

        $this->assertStatus(200, $res);
        $data = $res['body']['data'] ?? [];
        $this->assertEquals($this->group->id, $data['id'] ?? null);
    }

    public function testGetActivityReturns404ForNonExistentId(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/activities/999999');

        $this->assertNotFound($res);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/activities/:id/versions
    // ------------------------------------------------------------------

    public function testGetVersionsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/activities/' . $this->group->id . '/versions');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/activities/:id/signups
    // ------------------------------------------------------------------

    public function testGetSignupsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/activities/' . $this->group->id . '/signups');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/activities/:id/change-log
    // ------------------------------------------------------------------

    public function testGetChangeLogReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/activities/' . $this->group->id . '/change-log');

        $this->assertStatus(200, $res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/activities — requires activities.create
    // ------------------------------------------------------------------

    public function testCreateActivityReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/activities', [
            'title' => 'http-act-new',
            'body'  => 'test body',
        ]);
        $this->assertUnauthorized($res);
    }

    public function testCreateActivityReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-act-regular');
        $res = $this->post('/api/v1/activities', [
            'title' => 'http-act-new',
            'body'  => 'test body',
        ]);
        $this->assertForbidden($res);
    }

    public function testCreateActivityReturns201ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/activities', [
            'title' => 'http-act-created-' . uniqid(),
            'body'  => 'Integration test activity',
            'tags'  => ['sports'],
        ]);

        $this->assertStatus(201, $res);
        $this->assertSuccess($res);
        $this->assertArrayHasKey('id', $res['body']['data'] ?? []);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/activities/:id — requires activities.update
    // ------------------------------------------------------------------

    public function testUpdateActivityReturns401WhenUnauthenticated(): void
    {
        $res = $this->put('/api/v1/activities/' . $this->group->id, ['title' => 'updated']);
        $this->assertUnauthorized($res);
    }

    public function testUpdateActivityReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->put('/api/v1/activities/' . $this->group->id, [
            'title' => 'http-act-updated-title',
        ]);

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/activities/:id/publish — requires activities.publish
    // ------------------------------------------------------------------

    public function testPublishActivityReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/activities/' . $this->group->id . '/publish', []);
        $this->assertUnauthorized($res);
    }

    public function testPublishActivityReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-act-regular');
        $res = $this->post('/api/v1/activities/' . $this->group->id . '/publish', []);
        $this->assertForbidden($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/activities/:id/signups — requires activities.signup
    // ------------------------------------------------------------------

    public function testSignupReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/activities/' . $this->group->id . '/signups', []);
        $this->assertUnauthorized($res);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createPublishedActivity(string $title): ActivityGroup
    {
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        $v = new ActivityVersion();
        $v->group_id       = $group->id;
        $v->version_number = 1;
        $v->title          = $title;
        $v->body           = 'test body';
        $v->tags           = json_encode(['sports']);
        $v->state          = 'published';
        $v->eligibility_tags  = json_encode([]);
        $v->required_supplies = json_encode([]);
        $v->max_headcount  = 10;
        $v->published_at   = date('Y-m-d H:i:s', strtotime('-1 day'));
        $v->signup_end     = date('Y-m-d', strtotime('+30 days'));
        $v->save();

        return $group;
    }

    private function cleanupTestActivities(): void
    {
        $groups = ActivityVersion::where('title', 'like', 'http-act-%')->column('group_id');
        if (!empty($groups)) {
            ActivitySignup::whereIn('group_id', $groups)->delete();
            ActivityVersion::whereIn('group_id', $groups)->delete();
            ActivityGroup::whereIn('id', $groups)->delete();
        }
    }
}
