<?php

namespace app\service;

use app\model\ActivityGroup;
use app\model\ActivityVersion;
use app\model\ActivitySignup;

class RecommendationService
{
    const MAX_FAMILY_DIVERSITY_PCT = 0.4;
    const COLD_START_DAYS = 30;

    /**
     * Get personalized recommendations with diversity cap and dedup.
     */
    public function getRecommendations(int $userId, string $context = 'list', int $limit = 10): array
    {
        $signupHistory = ActivitySignup::where('user_id', $userId)->column('group_id');
        $isColdStart = empty($signupHistory);

        if ($isColdStart) {
            return [
                'context' => $context,
                'list' => $this->getColdStartRecommendations($limit),
            ];
        }

        $userTags = $this->getUserInterestTags($signupHistory);

        $candidates = ActivityVersion::where('state', 'published')
            ->where('signup_end', '>=', date('Y-m-d'))
            ->order('published_at', 'desc')
            ->limit($limit * 5)
            ->select();

        $results = [];
        $tagCounts = [];
        $seenGroupIds = [];
        $maxPerTag = max(1, (int) ceil($limit * self::MAX_FAMILY_DIVERSITY_PCT));

        foreach ($candidates as $v) {
            if (count($results) >= $limit) break;

            $tags = json_decode($v->tags, true) ?: [];

            // Dedup: skip if same group already recommended
            if (isset($seenGroupIds[$v->group_id])) {
                continue;
            }

            // Per-tag diversity cap: skip if any tag already hit its limit
            if (!empty($tags) && $this->exceedsTagDiversityCap($tags, $tagCounts, $maxPerTag)) {
                continue;
            }

            // Skip activities already signed up for
            if (in_array($v->group_id, $signupHistory)) {
                continue;
            }

            $score = $this->scoreActivity($v, $userTags, $tags);

            $this->incrementTagCounts($tags, $tagCounts);
            $seenGroupIds[$v->group_id] = true;

            $results[] = [
                'id' => $v->group_id,
                'title' => $v->title,
                'tags' => $tags,
                'signup_count' => ActivitySignup::where('group_id', $v->group_id)
                    ->whereIn('status', ['confirmed', 'pending_acknowledgement'])
                    ->count(),
                'published_at' => $v->published_at,
                'score' => $score,
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        $results = array_slice($results, 0, $limit);

        // Remove score from output
        $results = array_map(function($r) {
            unset($r['score']);
            return $r;
        }, $results);

        return [
            'context' => $context,
            'list' => $results,
        ];
    }

    /**
     * Get popular activities (fallback for cold start) based on last 30 days.
     */
    public function getPopular(int $limit = 10): array
    {
        return $this->getColdStartRecommendations($limit);
    }

    /**
     * Cold-start: popular tags from last 30 days.
     */
    protected function getColdStartRecommendations(int $limit): array
    {
        $cutoff = date('Y-m-d', strtotime('-' . self::COLD_START_DAYS . ' days'));

        $activities = ActivityVersion::where('state', 'published')
            ->where('published_at', '>=', $cutoff)
            ->order('published_at', 'desc')
            ->limit($limit * 3)
            ->select();

        $results = [];
        $tagCounts = [];
        $seenGroupIds = [];
        $maxPerTag = max(1, (int) ceil($limit * self::MAX_FAMILY_DIVERSITY_PCT));

        foreach ($activities as $v) {
            if (count($results) >= $limit) break;

            // Dedup by group_id
            if (isset($seenGroupIds[$v->group_id])) {
                continue;
            }

            $tags = json_decode($v->tags, true) ?: [];

            // Per-tag 40% diversity cap
            if (!empty($tags) && $this->exceedsTagDiversityCap($tags, $tagCounts, $maxPerTag)) {
                continue;
            }

            $this->incrementTagCounts($tags, $tagCounts);
            $seenGroupIds[$v->group_id] = true;

            $signupCount = ActivitySignup::where('group_id', $v->group_id)
                ->whereIn('status', ['confirmed', 'pending_acknowledgement'])
                ->count();

            $results[] = [
                'id' => $v->group_id,
                'title' => $v->title,
                'tags' => $tags,
                'signup_count' => $signupCount,
                'published_at' => $v->published_at,
            ];
        }

        // Sort by signup count (popularity)
        usort($results, fn($a, $b) => $b['signup_count'] <=> $a['signup_count']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Get user interest tags from signup history.
     */
    protected function getUserInterestTags(array $groupIds): array
    {
        $tagCounts = [];
        foreach ($groupIds as $groupId) {
            $version = ActivityVersion::where('group_id', $groupId)
                ->order('version_number', 'desc')
                ->find();
            if ($version) {
                $tags = json_decode($version->tags, true) ?: [];
                foreach ($tags as $tag) {
                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                }
            }
        }
        arsort($tagCounts);
        return array_keys(array_slice($tagCounts, 0, 10, true));
    }

    /**
     * Determine activity family using all tags (not just first).
     * Returns a composite key from sorted tags for proper per-tag diversity capping.
     */
    protected function getActivityFamily(ActivityVersion $v): string
    {
        $tags = json_decode($v->tags, true) ?: [];
        if (empty($tags)) {
            return 'group_' . $v->group_id;
        }
        // Use sorted tags as family key for multi-tag diversity
        $sorted = $tags;
        sort($sorted);
        return implode('|', $sorted);
    }

    /**
     * Check per-tag diversity caps across all tags of an activity.
     * Returns true if any tag exceeds the max per-family limit.
     */
    protected function exceedsTagDiversityCap(array $activityTags, array &$tagCounts, int $maxPerTag): bool
    {
        foreach ($activityTags as $tag) {
            if (($tagCounts[$tag] ?? 0) >= $maxPerTag) {
                return true;
            }
        }
        return false;
    }

    /**
     * Increment per-tag counts for diversity tracking.
     */
    protected function incrementTagCounts(array $activityTags, array &$tagCounts): void
    {
        foreach ($activityTags as $tag) {
            $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
        }
    }

    /**
     * Get order recommendations based on user's past order history.
     * Recommends orders from the same activities the user has previously ordered from.
     */
    public function getOrderRecommendations(int $userId, int $limit = 10): array
    {
        $pastOrders = \app\model\Order::where('created_by', $userId)->select();
        $activityIds = array_unique(array_column($pastOrders->toArray(), 'activity_id'));

        if (empty($activityIds)) {
            // Cold start: return recent orders from any activity
            $candidates = \app\model\Order::where('state', '!=', 'canceled')
                ->order('id', 'desc')
                ->limit($limit)
                ->select();
        } else {
            // Recommend orders from the same activities (excluding own orders)
            $candidates = \app\model\Order::whereIn('activity_id', $activityIds)
                ->where('created_by', '!=', $userId)
                ->where('state', '!=', 'canceled')
                ->order('id', 'desc')
                ->limit($limit * 3)
                ->select();
        }

        $seenOrderIds = [];
        $seenActivityIds = [];
        $results = [];
        foreach ($candidates as $o) {
            if (count($results) >= $limit) break;
            // Dedup by order id
            if (isset($seenOrderIds[$o->id])) continue;
            // Dedup by order-family (activity_id) — at most one order per activity
            if (isset($seenActivityIds[$o->activity_id])) continue;
            $seenOrderIds[$o->id] = true;
            $seenActivityIds[$o->activity_id] = true;
            $results[] = [
                'id' => $o->id,
                'activity_id' => $o->activity_id,
                'state' => $o->state,
                'amount' => $o->amount,
                'created_at' => $o->created_at,
            ];
        }

        return ['list' => $results];
    }

    /**
     * Score activity based on tag overlap, recency, popularity, views, and saves signals.
     * NOTE: "saves" signal is proxied by view_count from the search index, since there is
     * no separate saves/bookmarks table. A future enhancement should replace this with a
     * dedicated saves table.
     */
    protected function scoreActivity(ActivityVersion $v, array $userTags, array $activityTags): float
    {
        $score = 0.0;

        // Tag overlap with user interests (weighted by position in user's interest list)
        foreach ($activityTags as $tag) {
            $position = array_search($tag, $userTags);
            if ($position !== false) {
                // Higher weight for stronger interests (earlier in sorted list)
                $score += 1.0 + (0.5 / ($position + 1));
            }
        }

        // Recency bonus
        if ($v->published_at) {
            $daysSince = max(1, (time() - strtotime($v->published_at)) / 86400);
            $score += 2.0 / $daysSince;
        }

        // Popularity signal: signup count
        $signups = ActivitySignup::where('group_id', $v->group_id)
            ->whereIn('status', ['confirmed', 'pending_acknowledgement'])
            ->count();
        $score += min(1.5, $signups / 30.0);

        // Views/saves signal: use search index view_count as proxy for both views and saves
        // (No dedicated saves table exists; view_count captures engagement intent)
        $searchEntry = \app\model\SearchIndex::where('entity_type', 'activity')
            ->where('entity_id', $v->group_id)
            ->find();
        if ($searchEntry) {
            $viewCount = $searchEntry->view_count ?? 0;
            $score += min(1.0, $viewCount / 100.0);
        }

        // Headcount scarcity bonus (activities close to full get a boost)
        if ($v->max_headcount > 0) {
            $fillRatio = $signups / $v->max_headcount;
            if ($fillRatio >= 0.7 && $fillRatio < 1.0) {
                $score += 0.5; // Urgency bonus for nearly-full activities
            }
        }

        return $score;
    }
}
