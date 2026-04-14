<?php

namespace app\service;

use app\model\SearchIndex;
use app\model\ActivityGroup;
use app\model\ActivityVersion;
use app\model\Order;

class SearchService
{
    protected PinyinService $pinyinService;
    protected SpellCorrectionService $spellService;

    public function __construct()
    {
        $this->pinyinService = new PinyinService();
        $this->spellService = new SpellCorrectionService();
    }

    /**
     * Search entities with full-text, pinyin, spell correction, sorting, and highlighting.
     * @param string $sort One of: relevance, recency, popularity, reply_count
     * @param bool $highlight Whether to include highlighted fields
     * @param string $author Filter by creator username/id
     * @param string $tags Comma-separated tag filter
     * @param int $replyCountMin Minimum view_count proxy for engagement filtering
     */
    public function search(string $query, string $type = '', int $page = 1, int $limit = 20, string $sort = 'relevance', bool $highlight = true, string $author = '', string $tags = '', int $replyCountMin = 0): array
    {
        $normalized = $this->normalizeText($query);
        $pinyinSearch = PinyinService::toPinyin($query);

        $queryBuilder = SearchIndex::where(function($q) use ($query, $normalized, $pinyinSearch) {
            $q->whereOr('title', 'like', "%{$query}%");
            $q->whereOr('body', 'like', "%{$query}%");
            $q->whereOr('normalized_text', 'like', "%{$normalized}%");
            $q->whereOr('pinyin_text', 'like', "%{$pinyinSearch}%");
        });

        if (!empty($type)) {
            $queryBuilder->where('entity_type', $type);
        }

        // Filter by author (creator username or id stored in author field)
        if (!empty($author)) {
            $queryBuilder->where('author', 'like', "%{$author}%");
        }

        // Filter by tags (comma-separated list; each tag must match in the JSON tags field)
        if (!empty($tags)) {
            $tagList = array_filter(array_map('trim', explode(',', $tags)));
            foreach ($tagList as $tag) {
                $tag = addslashes($tag);
                $queryBuilder->where('tags', 'like', "%\"{$tag}\"%");
            }
        }

        // Filter by engagement proxy: view_count >= reply_count_min
        if ($replyCountMin > 0) {
            $queryBuilder->where('view_count', '>=', $replyCountMin);
        }

        // Apply sorting
        switch ($sort) {
            case 'recency':
                $queryBuilder->order('updated_at', 'desc');
                break;
            case 'popularity':
                $queryBuilder->order('view_count', 'desc');
                break;
            case 'reply_count':
                // reply_count sort uses view_count as engagement proxy (no separate reply table)
                $queryBuilder->order('view_count', 'desc');
                break;
            case 'relevance':
            default:
                // Relevance: title matches first, then by recency
                // Escape user input to prevent SQL injection via orderRaw
                $safeQuery = addslashes(str_replace(['%', '_'], ['\%', '\_'], $query));
                $queryBuilder->orderRaw("CASE WHEN title LIKE '%{$safeQuery}%' THEN 0 ELSE 1 END ASC, updated_at DESC");
                break;
        }

        $total = $queryBuilder->count();
        $results = $queryBuilder->page($page, $limit)->select();

        $list = [];
        foreach ($results as $r) {
            $formatted = $this->formatResult($r);
            if ($highlight) {
                $formatted['highlights'] = $this->generateHighlights($r, $query);
            }
            $list[] = $formatted;
        }

        $didYouMean = $this->getDidYouMean($query);

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'query' => $query,
            'sort' => $sort,
            'did_you_mean' => $didYouMean,
        ];
    }

    /**
     * Logistics-specific search with tracking number tokenization and synonym handling.
     */
    public function searchLogistics(string $query, int $page = 1, int $limit = 20, string $sort = 'recency'): array
    {
        // Normalize tracking number: strip spaces/dashes for fuzzy match
        $tokenized = preg_replace('/[\s\-]/', '', $query);

        // Synonym expansion for common logistics terms
        $synonyms = [
            'delivered' => ['completed', 'received', 'arrived'],
            'shipped' => ['dispatched', 'sent', 'in_transit'],
            'pending' => ['waiting', 'processing', 'queued'],
            'delayed' => ['late', 'overdue', 'exception'],
        ];

        $queryLower = strtolower($query);
        $expandedTerms = [$query, $tokenized];
        foreach ($synonyms as $term => $synonymList) {
            if (strpos($queryLower, $term) !== false) {
                $expandedTerms = array_merge($expandedTerms, $synonymList);
            }
        }

        $queryBuilder = SearchIndex::where('entity_type', 'order')
            ->where(function($q) use ($expandedTerms) {
                foreach ($expandedTerms as $term) {
                    $q->whereOr('title', 'like', "%{$term}%");
                    $q->whereOr('body', 'like', "%{$term}%");
                    $q->whereOr('normalized_text', 'like', "%{$term}%");
                }
            });

        switch ($sort) {
            case 'recency':
                $queryBuilder->order('updated_at', 'desc');
                break;
            case 'relevance':
            default:
                $queryBuilder->order('id', 'desc');
                break;
        }

        $total = $queryBuilder->count();
        $results = $queryBuilder->page($page, $limit)->select();

        $list = [];
        foreach ($results as $r) {
            $formatted = $this->formatResult($r);
            $formatted['highlights'] = $this->generateHighlights($r, $query);
            $list[] = $formatted;
        }

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'query' => $query,
            'sort' => $sort,
        ];
    }

    /**
     * Get search suggestions with pinyin support.
     */
    public function suggest(string $query, int $limit = 10): array
    {
        $pinyinSuggestions = PinyinService::searchByPinyin($query);
        
        $results = SearchIndex::where('title', 'like', "{$query}%")
            ->limit($limit)
            ->select();

        $suggestions = [];
        foreach ($results as $r) {
            $suggestions[] = [
                'id' => $r->entity_id,
                'type' => $r->entity_type,
                'title' => $r->title,
            ];
        }

        foreach ($pinyinSuggestions as $py) {
            if (count($suggestions) >= $limit) break;
            $exists = false;
            foreach ($suggestions as $s) {
                if ($s['title'] === $py) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $suggestions[] = ['title' => $py, 'type' => 'pinyin'];
            }
        }

        return $suggestions;
    }

    /**
     * Index an entity with pinyin.
     */
    public function index(string $entityType, int $entityId, string $title, string $body, array $tags = []): void
    {
        $existing = SearchIndex::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->find();

        $pinyinText = PinyinService::convertToSearchable($title);

        if ($existing) {
            $existing->title = $title;
            $existing->body = $body;
            $existing->tags = json_encode($tags);
            $existing->normalized_text = $this->normalizeText($title . ' ' . $body);
            $existing->pinyin_text = $pinyinText;
            $existing->save();
        } else {
            $index = new SearchIndex();
            $index->entity_type = $entityType;
            $index->entity_id = $entityId;
            $index->title = $title;
            $index->body = $body;
            $index->tags = json_encode($tags);
            $index->normalized_text = $this->normalizeText($title . ' ' . $body);
            $index->pinyin_text = $pinyinText;
            $index->save();
        }
    }

    /**
     * Remove from index.
     */
    public function remove(string $entityType, int $entityId): void
    {
        SearchIndex::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->delete();
    }

    /**
     * Rebuild entire index.
     * Indexes the latest version of every non-archived activity, plus all orders.
     */
    public function rebuild(): void
    {
        SearchIndex::whereRaw('1=1')->delete();

        // Index latest version per activity group, skipping archived
        $versions = ActivityVersion::whereNotIn('state', ['archived'])
            ->order('version_number', 'desc')
            ->select();
        $indexed = [];
        foreach ($versions as $v) {
            if (!isset($indexed[$v->group_id])) {
                $this->index('activity', $v->group_id, $v->title, $v->body, json_decode($v->tags, true) ?: []);
                $indexed[$v->group_id] = true;
            }
        }

        $orders = Order::select();
        foreach ($orders as $o) {
            $this->index('order', $o->id, 'Order #' . $o->id, $o->notes ?: '', []);
        }
    }

    /**
     * Clean up orphaned entries.
     */
    public function cleanup(): int
    {
        $count = 0;
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));

        $activityIds = ActivityVersion::column('group_id');
        $orphans = SearchIndex::where('entity_type', 'activity')
            ->whereNotIn('entity_id', $activityIds)
            ->where('created_at', '<', $sevenDaysAgo)
            ->delete();
        $count += $orphans;

        $orderIds = Order::column('id');
        $orphans = SearchIndex::where('entity_type', 'order')
            ->whereNotIn('entity_id', $orderIds)
            ->where('created_at', '<', $sevenDaysAgo)
            ->delete();
        $count += $orphans;

        return $count;
    }

    /**
     * Get spell correction suggestions using Levenshtein distance.
     */
    public function correct(string $query): ?string
    {
        $allTerms = SearchIndex::column('title');
        SpellCorrectionService::loadDictionary($allTerms);
        
        return SpellCorrectionService::suggestCorrection($query);
    }

    /**
     * Get "Did you mean" suggestion.
     */
    public function getDidYouMean(string $query): ?string
    {
        $suggestion = $this->correct($query);
        if ($suggestion && $suggestion !== strtolower($query)) {
            return "Did you mean: {$suggestion}?";
        }
        return null;
    }

    protected function normalizeText(string $text): string
    {
        return strtolower(preg_replace('/[^a-z0-9\s]/', '', $text));
    }

    protected function formatResult(SearchIndex $r): array
    {
        return [
            'id' => $r->entity_id,
            'type' => $r->entity_type,
            'title' => $r->title,
            'body' => mb_substr($r->body, 0, 200),
            'tags' => json_decode($r->tags, true) ?: [],
            'url' => $this->getUrl($r->entity_type, $r->entity_id),
            'updated_at' => $r->updated_at,
        ];
    }

    /**
     * Generate field-level highlights for search results.
     */
    protected function generateHighlights(SearchIndex $r, string $query): array
    {
        $highlights = [];
        $escapedQuery = preg_quote($query, '/');

        if (preg_match("/(.{0,40})({$escapedQuery})(.{0,40})/i", $r->title, $m)) {
            $highlights['title'] = $m[1] . '<em>' . $m[2] . '</em>' . $m[3];
        }

        if (preg_match("/(.{0,60})({$escapedQuery})(.{0,60})/i", $r->body, $m)) {
            $highlights['body'] = '...' . $m[1] . '<em>' . $m[2] . '</em>' . $m[3] . '...';
        }

        return $highlights;
    }

    protected function getUrl(string $type, int $id): string
    {
        return match($type) {
            'activity' => "/src/views/activities/detail.html?id={$id}",
            'order' => "/src/views/orders/detail.html?id={$id}",
            default => "#",
        };
    }
}