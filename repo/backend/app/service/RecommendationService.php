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
        $familyCount = [];
        $seenFamilies = [];
        $maxPerFamily = max(1, (int) ceil($limit * self::MAX_FAMILY_DIVERSITY_PCT));

        foreach ($candidates as $v) {
            if (count($results) >= $limit) break;

            $tags = json_decode($v->tags, true) ?: [];
            $family = $this->getActivityFamily($v);

            // Dedup: skip if same family already recommended
            if (isset($seenFamilies[$family])) {
                // Allow up to maxPerFamily from same family
                if (($familyCount[$family] ?? 0) >= $maxPerFamily) {
                    continue;
                }
            }

            // Skip activities already signed up for
            if (in_array($v->group_id, $signupHistory)) {
                continue;
            }

            $score = $this->scoreActivity($v, $userTags, $tags);

            $familyCount[$family] = ($familyCount[$family] ?? 0) + 1;
            $seenFamilies[$family] = true;

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
        $familyCount = [];
        $maxPerFamily = max(1, (int) ceil($limit * self::MAX_FAMILY_DIVERSITY_PCT));

        foreach ($activities as $v) {
            if (count($results) >= $limit) break;

            $family = $this->getActivityFamily($v);
            if (($familyCount[$family] ?? 0) >= $maxPerFamily) {
                continue;
            }
            $familyCount[$family] = ($familyCount[$family] ?? 0) + 1;

            $signupCount = ActivitySignup::where('group_id', $v->group_id)
                ->whereIn('status', ['confirmed', 'pending_acknowledgement'])
                ->count();

            $results[] = [
                'id' => $v->group_id,
                'title' => $v->title,
                'tags' => json_decode($v->tags, true) ?: [],
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
     * Determine activity family (first tag or group_id as fallback).
     */
    protected function getActivityFamily(ActivityVersion $v): string
    {
        $tags = json_decode($v->tags, true) ?: [];
        return !empty($tags) ? $tags[0] : 'group_' . $v->group_id;
    }

    /**
     * Score activity based on tag overlap with user interests.
     */
    protected function scoreActivity(ActivityVersion $v, array $userTags, array $activityTags): float
    {
        $score = 0.0;
        foreach ($activityTags as $tag) {
            if (in_array($tag, $userTags)) {
                $score += 1.0;
            }
        }

        // Recency bonus
        if ($v->published_at) {
            $daysSince = max(1, (time() - strtotime($v->published_at)) / 86400);
            $score += 1.0 / $daysSince;
        }

        // Popularity signal
        $signups = ActivitySignup::where('group_id', $v->group_id)
            ->whereIn('status', ['confirmed', 'pending_acknowledgement'])
            ->count();
        $score += min(1.0, $signups / 50.0);

        return $score;
    }
}
