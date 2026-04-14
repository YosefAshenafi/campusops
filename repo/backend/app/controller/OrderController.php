<?php

namespace app\controller;

use think\Request;
use think\Response;
use app\service\OrderService;

class OrderController
{
    protected OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    /**
     * GET /api/v1/orders
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);
        $state = $request->get('state', '');
        $activityId = $request->get('activity_id', '');

        $userId = $request->user ? $request->user->id : 0;
        $role = $request->user ? $request->user->role : '';
        $result = $this->orderService->listOrders($page, $limit, $state, $activityId, $userId, $role);

        return json([
            'success' => true,
            'code' => 200,
            'data' => $result,
        ]);
    }

    /**
     * GET /api/v1/orders/:id
     */
    public function show(Request $request, int $id): Response
    {
        try {
            $userId = $request->user ? $request->user->id : 0;
            $role = $request->user ? $request->user->role : '';
            $order = $this->orderService->getOrder($id, $userId, $role);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $order,
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
     * GET /api/v1/orders/:id/history
     */
    public function history(Request $request, int $id): Response
    {
        try {
            $history = $this->orderService->getHistory($id);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $history,
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
     * POST /api/v1/orders
     */
    public function create(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        try {
            $order = $this->orderService->createOrder($data, $request->user);
            return json([
                'success' => true,
                'code' => 201,
                'data' => $order,
                'message' => 'Order created successfully',
            ], 201);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'code' => 400,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * PUT /api/v1/orders/:id
     */
    public function update(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);

        try {
            $order = $this->orderService->updateOrder($id, $data, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $order,
                'message' => 'Order updated successfully',
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
     * POST /api/v1/orders/:id/initiate-payment
     */
    public function initiatePayment(Request $request, int $id): Response
    {
        try {
            $order = $this->orderService->initiatePayment($id, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $order,
                'message' => 'Payment initiated',
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
     * POST /api/v1/orders/:id/confirm-payment
     */
    public function confirmPayment(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);

        try {
            $order = $this->orderService->confirmPayment($id, $data, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $order,
                'message' => 'Payment confirmed',
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
     * POST /api/v1/orders/:id/start-ticketing
     */
    public function startTicketing(Request $request, int $id): Response
    {
        try {
            $order = $this->orderService->startTicketing($id, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $order,
                'message' => 'Ticketing started',
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
     * POST /api/v1/orders/:id/ticket
     */
    public function ticket(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $ticketNumber = $data['ticket_number'] ?? '';

        try {
            $order = $this->orderService->addTicket($id, $ticketNumber, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $order,
                'message' => 'Ticket added',
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
     * POST /api/v1/orders/:id/refund
     */
    public function refund(Request $request, int $id): Response
    {
        try {
            $order = $this->orderService->refund($id, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $order,
                'message' => 'Order refunded',
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
     * POST /api/v1/orders/:id/cancel
     */
    public function cancel(Request $request, int $id): Response
    {
        try {
            $order = $this->orderService->cancel($id, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $order,
                'message' => 'Order cancelled',
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
     * POST /api/v1/orders/:id/close
     */
    public function close(Request $request, int $id): Response
    {
        try {
            $order = $this->orderService->close($id, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $order,
                'message' => 'Order closed',
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
     * PUT /api/v1/orders/:id/address
     */
    public function updateAddress(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);

        try {
            $order = $this->orderService->updateAddress($id, $data, $request->user);
            return json([
                'success' => true,
                'code' => 200,
                'data' => $order,
                'message' => 'Address updated',
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
     * POST /api/v1/orders/:id/request-address-correction
     */
    public function requestAddressCorrection(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $result = $this->orderService->requestAddressCorrection(
            $id,
            $data['address'] ?? [],
            $request->user->id,
            $request->user->role
        );

        $code = $result['success'] ? 200 : 400;
        return json(array_merge(['code' => $code], $result), $code);
    }

    /**
     * POST /api/v1/orders/:id/approve-address-correction
     */
    public function approveAddressCorrection(Request $request, int $id): Response
    {
        $result = $this->orderService->approveAddressCorrection($id, $request->user->id);

        $code = $result['success'] ? 200 : 400;
        return json(array_merge(['code' => $code], $result), $code);
    }
}