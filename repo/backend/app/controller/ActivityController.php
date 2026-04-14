<?php

namespace app\controller;

use think\Request;
use think\Response;
use app\service\ActivityService;

class ActivityController
{
    protected ActivityService $activityService;

    public function __construct()
    {
        $this->activityService = new ActivityService();
    }

    /**
     * GET /api/v1/activities
     * List activities with filters.
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);
        $state = $request->get('state', '');
        $tag = $request->get('tag', '');
        $keyword = $request->get('keyword', '');

        $result = $this->activityService->listActivities($page, $limit, $state, $tag, $keyword);

        return json([
            'success' => true,
            'code' => 200,
            'data' => $result,
        ]);
    }

    /**
     * GET /api/v1/activities/:id
     */
    public function show(Request $request, int $id): Response
    {
        try {
            $activity = $this->activityService->getActivity($id);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $activity,
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'code' => 404,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /api/v1/activities/:id/versions
     */
    public function versions(Request $request, int $id): Response
    {
        try {
            $versions = $this->activityService->getVersions($id);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $versions,
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'code' => 404,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /api/v1/activities/:id/signups
     */
    public function signups(Request $request, int $id): Response
    {
        try {
            $signups = $this->activityService->getSignups($id, $request->user->id, $request->user->role);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $signups,
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'code' => 404,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /api/v1/activities/:id/change-log
     */
    public function changeLog(Request $request, int $id): Response
    {
        try {
            $changelog = $this->activityService->getChangeLog($id);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $changelog,
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'code' => 404,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * POST /api/v1/activities
     * Create new activity.
     */
    public function create(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        try {
            (new \app\validate\ActivityValidate())->failException(true)->scene('create')->check($data);
            $activity = $this->activityService->createActivity($data, $request->user);
            return json([
                'success' => true,
                'code' => 201,
                'data' => $activity,
                'message' => 'Activity created successfully',
            ], 201);
        } catch (\think\exception\ValidateException $e) {
            return json([
                'success' => false,
                'code' => 422,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'code' => 400,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * PUT /api/v1/activities/:id
     * Update activity (creates new version if published).
     */
    public function update(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);

        try {
            (new \app\validate\ActivityValidate())->failException(true)->scene('update')->check($data);
            $activity = $this->activityService->updateActivity($id, $data, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $activity,
                'message' => 'Activity updated successfully',
            ]);
        } catch (\think\exception\ValidateException $e) {
            return json([
                'success' => false,
                'code' => 422,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            return json([
                'success' => false,
                'code' => $code,
                'error' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * POST /api/v1/activities/:id/publish
     */
    public function publish(Request $request, int $id): Response
    {
        try {
            $activity = $this->activityService->publishActivity($id, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $activity,
                'message' => 'Activity published successfully',
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            return json([
                'success' => false,
                'code' => $code,
                'error' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * POST /api/v1/activities/:id/start
     */
    public function start(Request $request, int $id): Response
    {
        try {
            $activity = $this->activityService->startActivity($id, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $activity,
                'message' => 'Activity started successfully',
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            return json([
                'success' => false,
                'code' => $code,
                'error' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * POST /api/v1/activities/:id/complete
     */
    public function complete(Request $request, int $id): Response
    {
        try {
            $activity = $this->activityService->completeActivity($id, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $activity,
                'message' => 'Activity completed successfully',
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            return json([
                'success' => false,
                'code' => $code,
                'error' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * POST /api/v1/activities/:id/archive
     */
    public function archive(Request $request, int $id): Response
    {
        try {
            $activity = $this->activityService->archiveActivity($id, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $activity,
                'message' => 'Activity archived successfully',
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            return json([
                'success' => false,
                'code' => $code,
                'error' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * POST /api/v1/activities/:id/signups
     * Sign up for activity.
     */
    public function signup(Request $request, int $id): Response
    {
        try {
            $signup = $this->activityService->signupUser($id, $request->user);
            return json([
                'success' => true,
                'code' => 201,
                'data' => $signup,
                'message' => 'Signed up successfully',
            ], 201);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            return json([
                'success' => false,
                'code' => $code,
                'error' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * DELETE /api/v1/activities/:id/signups/:signup_id
     * Cancel signup.
     */
    public function cancelSignup(Request $request, int $id, int $signupId): Response
    {
        try {
            $this->activityService->cancelSignup($id, $signupId, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'message' => 'Signup cancelled successfully',
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            return json([
                'success' => false,
                'code' => $code,
                'error' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * POST /api/v1/activities/:id/signups/:signup_id/acknowledge
     */
    public function acknowledge(Request $request, int $id, int $signupId): Response
    {
        try {
            $this->activityService->acknowledgeChanges($id, $signupId, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'message' => 'Changes acknowledged',
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            return json([
                'success' => false,
                'code' => $code,
                'error' => $e->getMessage(),
            ], $code);
        }
    }
}