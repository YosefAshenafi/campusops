<?php

namespace app\controller;

use think\Request;
use think\Response;
use app\service\SearchService;

class SearchController
{
    protected SearchService $searchService;

    public function __construct()
    {
        $this->searchService = new SearchService();
    }

    /**
     * GET /api/v1/search
     * Supports: q, type, page, limit, sort (recency|popularity|relevance), highlight (0|1)
     */
    public function index(Request $request): Response
    {
        $query = $request->get('q', '');
        $type = $request->get('type', '');
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);
        $sort = $request->get('sort', 'relevance');
        $highlight = (bool) $request->get('highlight', 1);
        $author = $request->get('author', '');
        $tags = $request->get('tags', '');
        $replyCountMin = (int) $request->get('reply_count_min', 0);

        if (strlen($query) < 2) {
            return json(['success' => true, 'code' => 200, 'data' => ['list' => [], 'total' => 0]]);
        }

        $result = $this->searchService->search($query, $type, $page, $limit, $sort, $highlight, $author, $tags, $replyCountMin);

        return json(['success' => true, 'code' => 200, 'data' => $result]);
    }

    /**
     * GET /api/v1/search/suggest
     */
    public function suggest(Request $request): Response
    {
        $query = $request->get('q', '');
        $limit = (int) $request->get('limit', 10);

        if (strlen($query) < 1) {
            return json(['success' => true, 'code' => 200, 'data' => []]);
        }

        $suggestions = $this->searchService->suggest($query, $limit);
        
        return json(['success' => true, 'code' => 200, 'data' => $suggestions]);
    }

    /**
     * GET /api/v1/search/logistics
     * Logistics-specific search with tracking number tokenization and synonym handling.
     */
    public function logistics(Request $request): Response
    {
        $query = $request->get('q', '');
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);
        $sort = $request->get('sort', 'recency');

        $status = $request->get('status', '');
        $carrier = $request->get('carrier', '');

        $result = $this->searchService->searchLogistics($query, $page, $limit, $sort, $status, $carrier);

        return json(['success' => true, 'code' => 200, 'data' => $result]);
    }

    /**
     * GET /api/v1/index/status
     */
    public function status(Request $request): Response
    {
        $total = \app\model\SearchIndex::count();
        
        return json(['success' => true, 'code' => 200, 'data' => ['total' => $total]]);
    }

    /**
     * POST /api/v1/index/rebuild
     */
    public function rebuild(Request $request): Response
    {
        $this->searchService->rebuild();
        
        return json(['success' => true, 'code' => 200, 'message' => 'Index rebuilt']);
    }

    /**
     * POST /api/v1/index/cleanup
     */
    public function cleanup(Request $request): Response
    {
        $count = $this->searchService->cleanup();
        
        return json(['success' => true, 'code' => 200, 'message' => "Cleaned up {$count} entries"]);
    }
}