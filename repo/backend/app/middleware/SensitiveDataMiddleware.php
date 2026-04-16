<?php

namespace app\middleware;

use think\Request;
use think\Response;

class SensitiveDataMiddleware
{
    protected static array $sensitiveFields = [
        'user' => ['password_hash', 'salt', 'invoice_address'],
        'order' => ['invoice_address'],
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        $response = $next($request);
        
        if ($request->user && $request->user->role !== 'administrator') {
            return $this->maskSensitiveData($response, $request);
        }

        return $response;
    }

    protected function maskSensitiveData(Response $response, Request $request): Response
    {
        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!$data || !isset($data['data'])) {
            return $response;
        }

        $entityType = $this->detectEntityType($request);

        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = $this->maskRecursive($data['data'], $entityType);
        }

        $response->content(json_encode($data));
        return $response;
    }

    protected function maskRecursive(array $data, string $entityType): array
    {
        $fields = self::$sensitiveFields[$entityType] ?? [];

        // Check if this is a list payload (e.g. data.list[*])
        if (isset($data['list']) && is_array($data['list'])) {
            foreach ($data['list'] as $key => $item) {
                if (is_array($item)) {
                    $data['list'][$key] = $this->maskFields($item, $fields);
                }
            }
            return $data;
        }

        // Check if this is a numerically indexed array (direct list)
        if (array_is_list($data)) {
            foreach ($data as $key => $item) {
                if (is_array($item)) {
                    $data[$key] = $this->maskFields($item, $fields);
                }
            }
            return $data;
        }

        // Single entity
        return $this->maskFields($data, $fields);
    }

    protected function maskFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }

        // Recurse into nested arrays/objects
        foreach ($data as $key => $value) {
            if (is_array($value) && !empty($fields)) {
                $data[$key] = $this->maskFields($value, $fields);
            }
        }

        return $data;
    }

    protected function detectEntityType(Request $request): string
    {
        // Use pathinfo() instead of path() — path() calls config() internally which
        // is not available on all Request instances (e.g. in the test context).
        $path = $request->pathinfo();
        if (strpos($path, 'users') !== false) return 'user';
        if (strpos($path, 'orders') !== false) return 'order';
        return '';
    }
}