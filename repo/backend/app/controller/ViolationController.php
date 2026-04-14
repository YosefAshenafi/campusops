<?php

namespace app\controller;

use think\Request;
use think\Response;
use app\service\ViolationService;

class ViolationController
{
    protected ViolationService $violationService;

    public function __construct()
    {
        $this->violationService = new ViolationService();
    }

    /**
     * GET /api/v1/violations/rules
     */
    public function rules(Request $request): Response
    {
        $result = $this->violationService->listRules();
        return json(['success' => true, 'code' => 200, 'data' => $result]);
    }

    /**
     * GET /api/v1/violations/rules/:id
     */
    public function ruleShow(Request $request, int $id): Response
    {
        try {
            $rule = $this->violationService->getRule($id);
            return json(['success' => true, 'code' => 200, 'data' => $rule]);
        } catch (\Exception $e) {
            return json(['success' => false, 'code' => 404, 'error' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/v1/violations/rules
     */
    public function ruleCreate(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        try {
            $rule = $this->violationService->createRule($data, $request->user);
            return json(['success' => true, 'code' => 201, 'data' => $rule, 'message' => 'Rule created'], 201);
        } catch (\Exception $e) {
            return json(['success' => false, 'code' => 400, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /api/v1/violations/rules/:id
     */
    public function ruleUpdate(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        try {
            $rule = $this->violationService->updateRule($id, $data, $request->user);
            return json(['success' => true, 'code' => 200, 'data' => $rule, 'message' => 'Rule updated']);
        } catch (\Exception $e) {
            return json(['success' => false, 'code' => 400, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/v1/violations/rules/:id
     */
    public function ruleDelete(Request $request, int $id): Response
    {
        try {
            $this->violationService->deleteRule($id, $request->user);
            return json(['success' => true, 'code' => 200, 'message' => 'Rule deleted']);
        } catch (\Exception $e) {
            return json(['success' => false, 'code' => 400, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/v1/violations
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);
        $userId = $request->get('user_id', '');
        $groupId = $request->get('group_id', '');

        $result = $this->violationService->listViolations($page, $limit, $userId, $groupId, $request->user->id, $request->user->role);
        return json(['success' => true, 'code' => 200, 'data' => $result]);
    }

    /**
     * GET /api/v1/violations/:id
     */
    public function show(Request $request, int $id): Response
    {
        try {
            $violation = $this->violationService->getViolation($id, $request->user->id, $request->user->role);
            return json(['success' => true, 'code' => 200, 'data' => $violation]);
        } catch (\Exception $e) {
            return json(['success' => false, 'code' => 404, 'error' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/v1/violations
     */
    public function create(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        try {
            $violation = $this->violationService->createViolation($data, $request->user);
            return json(['success' => true, 'code' => 201, 'data' => $violation, 'message' => 'Violation recorded'], 201);
        } catch (\Exception $e) {
            return json(['success' => false, 'code' => 400, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/v1/violations/user/:user_id
     */
    public function userViolations(Request $request, int $userId): Response
    {
        $result = $this->violationService->getUserViolations($userId);
        return json(['success' => true, 'code' => 200, 'data' => $result]);
    }

    /**
     * GET /api/v1/violations/group/:group_id
     */
    public function groupViolations(Request $request, int $groupId): Response
    {
        $result = $this->violationService->getGroupViolations($groupId);
        return json(['success' => true, 'code' => 200, 'data' => $result]);
    }

    /**
     * POST /api/v1/violations/:id/appeal
     */
    public function appeal(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        try {
            $this->violationService->submitAppeal($id, $data, $request->user);
            return json(['success' => true, 'code' => 200, 'message' => 'Appeal submitted']);
        } catch (\Exception $e) {
            return json(['success' => false, 'code' => 400, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/v1/violations/:id/review
     */
    public function review(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        try {
            $this->violationService->reviewAppeal($id, $data, $request->user);
            return json(['success' => true, 'code' => 200, 'message' => 'Appeal reviewed']);
        } catch (\Exception $e) {
            return json(['success' => false, 'code' => 400, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/v1/violations/:id/final-decision
     */
    public function finalDecision(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        try {
            $this->violationService->finalDecision($id, $data, $request->user);
            return json(['success' => true, 'code' => 200, 'message' => 'Decision recorded']);
        } catch (\Exception $e) {
            return json(['success' => false, 'code' => 400, 'error' => $e->getMessage()], 400);
        }
    }
}