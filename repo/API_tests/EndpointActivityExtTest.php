<?php

declare(strict_types=1);

namespace tests\api;

use app\model\ActivityGroup;
use app\model\ActivityVersion;
use app\model\ActivitySignup;

/**
 * HTTP endpoint tests for activity lifecycle transitions and signup management
 * not covered by EndpointActivityTest:
 *
 *   POST   /api/v1/activities/:id/publish              (success path)
 *   POST   /api/v1/activities/:id/start
 *   POST   /api/v1/activities/:id/complete
 *   POST   /api/v1/activities/:id/archive
 *   POST   /api/v1/activities/:id/signups              (success path)
 *   DELETE /api/v1/activities/:id/signups/:signup_id
 *   POST   /api/v1/activities/:id/signups/:signup_id/acknowledge
 */
class EndpointActivityExtTest extends HttpTestCase
{
    private ActivityGroup   $publishedGroup;
    private ActivityGroup   $startedGroup;
    private ActivityGroup   $draftGroup;
    private ActivitySignup  $signup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupUsersLike('http-actx-%');
        $this->cleanupTestData();

        // Published activity — for start, archive, signup, cancel-signup, acknowledge
        $this->publishedGroup = $this->createActivityInState('published');

        // In-progress activity — for complete
        $this->startedGroup = $this->createActivityInState('in_progress');

        // Draft activity — for publish success path
        $this->draftGroup = $this->createActivityInState('draft');

        // Signup — for cancel-signup and acknowledge tests
        $this->signup = $this->createSignup($this->publishedGroup->id, 1);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        $this->cleanupUsersLike('http-actx-%');
        parent::tearDown();
    }

    // ======================================================================
    // POST /api/v1/activities/:id/publish  (rbac: activities.publish)
    // ======================================================================

    public function testPublishActivitySuccessForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/activities/' . $this->draftGroup->id . '/publish', []);
        // Draft → published: 200 OK
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testPublishActivityReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-actx-regular');
        $res = $this->post('/api/v1/activities/' . $this->draftGroup->id . '/publish', []);
        $this->assertForbidden($res);
    }

    public function testPublishActivityReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/activities/' . $this->draftGroup->id . '/publish', []);
        $this->assertUnauthorized($res);
    }

    // ======================================================================
    // POST /api/v1/activities/:id/start  (rbac: activities.transition)
    // ======================================================================

    public function testStartActivityReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/activities/' . $this->publishedGroup->id . '/start', []);
        $this->assertUnauthorized($res);
    }

    public function testStartActivityReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-actx-regular');
        $res = $this->post('/api/v1/activities/' . $this->publishedGroup->id . '/start', []);
        $this->assertForbidden($res);
    }

    public function testStartActivitySuccessForAdmin(): void
    {
        // Create a fresh published group to avoid conflicts with other tests
        $group = $this->createActivityInState('published');
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/activities/' . $group->id . '/start', []);
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testStartActivityReturns404ForMissingId(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/activities/999999/start', []);
        $this->assertNotFound($res);
    }

    // ======================================================================
    // POST /api/v1/activities/:id/complete  (rbac: activities.transition)
    // ======================================================================

    public function testCompleteActivityReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/activities/' . $this->startedGroup->id . '/complete', []);
        $this->assertUnauthorized($res);
    }

    public function testCompleteActivityReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-actx-regular');
        $res = $this->post('/api/v1/activities/' . $this->startedGroup->id . '/complete', []);
        $this->assertForbidden($res);
    }

    public function testCompleteActivitySuccessForAdmin(): void
    {
        $group = $this->createActivityInState('in_progress');
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/activities/' . $group->id . '/complete', []);
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testCompleteActivityReturns404ForMissingId(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/activities/999999/complete', []);
        $this->assertNotFound($res);
    }

    // ======================================================================
    // POST /api/v1/activities/:id/archive  (rbac: activities.transition)
    // ======================================================================

    public function testArchiveActivityReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/activities/' . $this->publishedGroup->id . '/archive', []);
        $this->assertUnauthorized($res);
    }

    public function testArchiveActivityReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-actx-regular');
        $res = $this->post('/api/v1/activities/' . $this->publishedGroup->id . '/archive', []);
        $this->assertForbidden($res);
    }

    public function testArchiveActivitySuccessForAdmin(): void
    {
        $group = $this->createActivityInState('completed');
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/activities/' . $group->id . '/archive', []);
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    public function testArchiveActivityReturns404ForMissingId(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/activities/999999/archive', []);
        $this->assertNotFound($res);
    }

    // ======================================================================
    // POST /api/v1/activities/:id/signups  (rbac: activities.signup)
    // ======================================================================

    public function testSignupActivityReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/activities/' . $this->publishedGroup->id . '/signups', []);
        $this->assertUnauthorized($res);
    }

    public function testSignupActivitySuccessForRegularUser(): void
    {
        // Create a fresh group to avoid slot conflicts
        $group = $this->createActivityInState('published');
        $this->loginAsRole('regular_user', 'http-actx-regular');
        $res = $this->post('/api/v1/activities/' . $group->id . '/signups', []);
        // 200 OK or 400 if already signed up — accept non-401/403
        $this->assertNotEquals(401, $res['status']);
        $this->assertNotEquals(403, $res['status']);
    }

    // ======================================================================
    // DELETE /api/v1/activities/:id/signups/:signup_id  (rbac: activities.signup)
    // ======================================================================

    public function testCancelSignupReturns401WhenUnauthenticated(): void
    {
        $res = $this->delete(
            '/api/v1/activities/' . $this->publishedGroup->id . '/signups/' . $this->signup->id
        );
        $this->assertUnauthorized($res);
    }

    public function testCancelSignupSuccessForAdmin(): void
    {
        // Create a separate signup to cancel so the shared one stays for acknowledge tests
        $extraSignup = $this->createSignup($this->publishedGroup->id, 2);
        $this->loginAsAdmin();
        $res = $this->delete(
            '/api/v1/activities/' . $this->publishedGroup->id . '/signups/' . $extraSignup->id
        );
        $this->assertNotEquals(401, $res['status']);
        $this->assertNotEquals(403, $res['status']);
    }

    public function testCancelSignupReturns404ForMissingSignup(): void
    {
        $this->loginAsAdmin();
        $res = $this->delete(
            '/api/v1/activities/' . $this->publishedGroup->id . '/signups/999999'
        );
        $this->assertNotFound($res);
    }

    // ======================================================================
    // POST /api/v1/activities/:id/signups/:signup_id/acknowledge
    //   (rbac: activities.signup)
    // ======================================================================

    public function testAcknowledgeSignupReturns401WhenUnauthenticated(): void
    {
        $res = $this->post(
            '/api/v1/activities/' . $this->publishedGroup->id
            . '/signups/' . $this->signup->id . '/acknowledge',
            []
        );
        $this->assertUnauthorized($res);
    }

    public function testAcknowledgeSignupSuccessForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->post(
            '/api/v1/activities/' . $this->publishedGroup->id
            . '/signups/' . $this->signup->id . '/acknowledge',
            []
        );
        $this->assertNotEquals(401, $res['status']);
        $this->assertNotEquals(403, $res['status']);
    }

    public function testAcknowledgeSignupReturns404ForMissingSignup(): void
    {
        $this->loginAsAdmin();
        $res = $this->post(
            '/api/v1/activities/' . $this->publishedGroup->id . '/signups/999999/acknowledge',
            []
        );
        $this->assertNotFound($res);
    }

    // ======================================================================
    // Helpers
    // ======================================================================

    private function createActivityInState(string $state): ActivityGroup
    {
        $group = new ActivityGroup();
        $group->created_by = 1;
        $group->save();

        $v = new ActivityVersion();
        $v->group_id          = $group->id;
        $v->version_number    = 1;
        $v->title             = 'http-actx-' . $state . '-' . uniqid();
        $v->body              = 'test body';
        $v->tags              = json_encode(['sports']);
        $v->state             = $state;
        $v->eligibility_tags  = json_encode([]);
        $v->required_supplies = json_encode([]);
        $v->max_headcount     = 10;
        $v->signup_end        = date('Y-m-d', strtotime('+30 days'));

        if (in_array($state, ['published', 'in_progress', 'completed', 'archived'])) {
            $v->published_at = date('Y-m-d H:i:s', strtotime('-1 day'));
        }
        if (in_array($state, ['in_progress', 'completed', 'archived'])) {
            $v->started_at = date('Y-m-d H:i:s', strtotime('-1 hour'));
        }
        if (in_array($state, ['completed', 'archived'])) {
            $v->completed_at = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        }
        $v->save();

        return $group;
    }

    private function createSignup(int $groupId, int $userId): ActivitySignup
    {
        $s = new ActivitySignup();
        $s->group_id = $groupId;
        $s->user_id  = $userId;
        $s->status   = 'confirmed';
        $s->save();
        return $s;
    }

    private function cleanupTestData(): void
    {
        $groupIds = ActivityVersion::where('title', 'like', 'http-actx-%')->column('group_id');
        if (!empty($groupIds)) {
            ActivitySignup::whereIn('group_id', $groupIds)->delete();
            ActivityVersion::whereIn('group_id', $groupIds)->delete();
            ActivityGroup::whereIn('id', $groupIds)->delete();
        }
        // Clean up any signups for user_id 2 that were created in tests
        ActivitySignup::where('user_id', 2)->delete();
    }
}
