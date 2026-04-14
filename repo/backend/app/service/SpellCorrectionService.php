<?php

namespace app\service;

class SpellCorrectionService
{
    protected static int $maxDistance = 3;

    protected static array $dictionary = [];

    public static function loadDictionary(array $terms): void
    {
        foreach ($terms as $term) {
            $normalized = strtolower(trim($term));
            if (!empty($normalized)) {
                self::$dictionary[] = $normalized;
            }
        }
    }

    public static function levenshteinDistance(string $s1, string $s2): int
    {
        $s1 = strtolower($s1);
        $s2 = strtolower($s2);

        if ($s1 === $s2) {
            return 0;
        }

        $len1 = strlen($s1);
        $len2 = strlen($s2);

        if ($len1 === 0) {
            return $len2;
        }
        if ($len2 === 0) {
            return $len1;
        }

        $distance = range(0, $len2);
        $previousDistance = range(0, $len1);

        for ($i = 1; $i <= $len1; $i++) {
            $currentDistance = [$i];
            $previousDistance[0] = $i;

            for ($j = 1; $j <= $len2; $j++) {
                $cost = ($s1[$i - 1] === $s2[$j - 1]) ? 0 : 1;
                $currentDistance[$j] = min(
                    $previousDistance[$j] + 1,
                    $currentDistance[$j - 1] + 1,
                    $previousDistance[$j - 1] + $cost
                );
            }

            $distance = $previousDistance;
            $previousDistance = $currentDistance;
        }

        return $previousDistance[$len2];
    }

    public static function findSuggestions(string $query, int $maxSuggestions = 5): array
    {
        $query = strtolower(trim($query));
        
        if (empty($query)) {
            return [];
        }

        if (empty(self::$dictionary)) {
            self::buildDefaultDictionary();
        }

        $candidates = [];

        foreach (self::$dictionary as $term) {
            $distance = self::levenshteinDistance($query, $term);

            if ($distance <= self::$maxDistance) {
                $score = 1 - ($distance / max(strlen($query), strlen($term)));
                $candidates[$term] = [
                    'term' => $term,
                    'distance' => $distance,
                    'score' => $score
                ];
            }
        }

        usort($candidates, function($a, $b) use ($query) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            return strcmp($a['term'], $query);
        });

        return array_slice($candidates, 0, $maxSuggestions);
    }

    public static function suggestCorrection(string $query): ?string
    {
        $suggestions = self::findSuggestions($query, 1);

        if (!empty($suggestions) && $suggestions[0]['distance'] < strlen($query)) {
            return $suggestions[0]['term'];
        }

        return null;
    }

    public static function buildDefaultDictionary(): void
    {
        $commonTerms = [
            'activity', 'activities', 'admin', 'administrator', 'appeal',
            'cancel', 'canceled', 'cancellation', 'checklist', 'checklists',
            'complete', 'completed', 'confirm', 'confirmation', 'create',
            'dashboard', 'delete', 'delivery', 'draft', 'edit', 'export',
            'forgot', 'forgot password', 'help', 'home', 'login', 'logout',
            'notification', 'notifications', 'order', 'orders', 'paid',
            'password', 'payment', 'pending', 'publish', 'published',
            'refund', 'report', 'reports', 'reset', 'review', 'reviews',
            'search', 'settings', 'shipment', 'shipments', 'signup',
            'signups', 'staff', 'staffing', 'status', 'task', 'tasks',
            'team lead', 'team_lead', 'update', 'user', 'users', 'violation',
            'violations', 'welcome'
        ];

        self::loadDictionary($commonTerms);
    }

    public static function addToDictionary(string $term): void
    {
        $normalized = strtolower(trim($term));
        if (!empty($normalized) && !in_array($normalized, self::$dictionary)) {
            self::$dictionary[] = $normalized;
        }
    }

    public static function getDidYouMean(string $query): ?string
    {
        $suggestion = self::suggestCorrection($query);
        
        if ($suggestion && $suggestion !== strtolower($query)) {
            return "Did you mean: {$suggestion}?";
        }

        return null;
    }
}