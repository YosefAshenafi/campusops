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
     * Search entities with full-text, pinyin, and spell correction.
     */
    public function search(string $query, string $type = '', int $page = 1, int $limit = 20): array
    {
        $queryLower = strtolower($query);
        $normalized = $this->normalizeText($query);
        $pinyinSearch = PinyinService::toPinyin($query);

        $where = function($q) use ($query, $normalized, $pinyinSearch) {
            $q->whereOr(function($sq) use ($query, $normalized, $pinyinSearch) {
                $sq->where('title', 'like', "%{$query}%");
                $sq->whereOr('body', 'like', "%{$query}%");
                $sq->whereOr('normalized_text', 'like', "%{$normalized}%");
                $sq->whereOr('pinyin_text', 'like', "%{$pinyinSearch}%");
            });
        };

        if (!empty($type)) {
            $where->where('entity_type', $type);
        }

        $total = SearchIndex::where($where)->count();
        $results = SearchIndex::where($where)->page($page, $limit)->select();

        return [
            'list' => array_map(fn($r) => $this->formatResult($r), $results),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'query' => $query,
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

        $suggestions = array_map(fn($r) => [
            'id' => $r->entity_id,
            'type' => $r->entity_type,
            'title' => $r->title,
        ], $results);

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
     */
    public function rebuild(): void
    {
        SearchIndex::whereRaw('1=1')->delete();

        $activities = ActivityVersion::where('state', 'published')->select();
        foreach ($activities as $v) {
            $this->index('activity', $v->group_id, $v->title, $v->body, json_decode($v->tags, true) ?: []);
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

        $activityIds = ActivityVersion::column('group_id');
        $orphans = SearchIndex::where('entity_type', 'activity')
            ->whereNotIn('entity_id', $activityIds)
            ->delete();
        $count += $orphans;

        $orderIds = Order::column('id');
        $orphans = SearchIndex::where('entity_type', 'order')
            ->whereNotIn('entity_id', $orderIds)
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
            'body' => mb_substr($r->body, 0, 100),
            'url' => $this->getUrl($r->entity_type, $r->entity_id),
        ];
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