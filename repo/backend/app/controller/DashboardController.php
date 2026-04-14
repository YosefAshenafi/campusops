<?php

namespace app\controller;

use think\Request;
use think\Response;
use app\service\DashboardService;
use app\service\ExportService;

class DashboardController
{
    protected DashboardService $dashboardService;

    public function __construct()
    {
        $this->dashboardService = new DashboardService();
    }

    public function index(Request $request): Response
    {
        $data = $this->dashboardService->getDefault($request->user->id);
        return json(['success' => true, 'code' => 200, 'data' => $data]);
    }

    public function custom(Request $request): Response
    {
        $data = $this->dashboardService->getCustom($request->user->id);
        return json(['success' => true, 'code' => 200, 'data' => $data]);
    }

    public function createCustom(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $result = $this->dashboardService->saveCustom($request->user->id, $data);
        return json(['success' => true, 'code' => 201, 'data' => $result], 201);
    }

    public function updateCustom(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        try {
            $result = $this->dashboardService->updateCustom($id, $request->user->id, $data);
            return json(['success' => true, 'code' => 200, 'data' => $result]);
        } catch (\Exception $e) {
            return json(['success' => false, 'code' => 404, 'error' => $e->getMessage()], 404);
        }
    }

    public function deleteCustom(Request $request): Response
    {
        $this->dashboardService->deleteCustom($request->user->id);
        return json(['success' => true, 'code' => 200, 'data' => null]);
    }

    public function favorites(Request $request): Response
    {
        $data = $this->dashboardService->getFavorites($request->user->id);
        return json(['success' => true, 'code' => 200, 'data' => $data]);
    }

    public function addFavorite(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $widgetId = $data['widget_id'] ?? '';
        if (empty($widgetId)) {
            return json(['success' => false, 'code' => 400, 'error' => 'widget_id is required'], 400);
        }
        $result = $this->dashboardService->favoriteWidget($request->user->id, $widgetId);
        return json(['success' => true, 'code' => 200, 'data' => $result]);
    }

    public function removeFavorite(Request $request, string $widgetId): Response
    {
        $result = $this->dashboardService->unfavoriteWidget($request->user->id, $widgetId);
        return json(['success' => true, 'code' => 200, 'data' => $result]);
    }

    public function drill(Request $request, string $widgetId): Response
    {
        $data = $this->dashboardService->getDrillData($widgetId);
        return json(['success' => true, 'code' => 200, 'data' => $data]);
    }

    public function snapshot(Request $request): Response
    {
        $exportService = new ExportService();
        $format = $request->get('format', 'png');
        $dashData = $this->dashboardService->getDefault($request->user->id);
        $widgets = $dashData['widgets'];

        $flatData = [];
        if (!empty($widgets['orders_by_state'])) {
            foreach ($widgets['orders_by_state'] as $item) {
                $flatData['Orders - ' . $item['state']] = $item['count'];
            }
        }
        if (!empty($widgets['activities_by_state'])) {
            foreach ($widgets['activities_by_state'] as $item) {
                $flatData['Activities - ' . $item['state']] = $item['count'];
            }
        }

        switch ($format) {
            case 'pdf':
                $filepath = $exportService->exportToPdf($flatData, 'dashboard_snapshot', $request->user->id);
                break;
            case 'xlsx':
            case 'excel':
                $excelData = array_map(fn($k, $v) => [$k, $v], array_keys($flatData), array_values($flatData));
                $filepath = $exportService->exportToExcel($excelData, 'dashboard_snapshot', $request->user->id);
                break;
            case 'png':
            default:
                $filepath = $exportService->exportToPng($flatData, 'dashboard_snapshot', $request->user->id);
                break;
        }

        return json([
            'success' => true,
            'code' => 200,
            'data' => [
                'file' => basename($filepath),
                'format' => $format,
                'download_url' => '/api/v1/export/download?file=' . urlencode(basename($filepath)),
            ],
        ]);
    }
}
