<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConfigApiController extends Controller
{
    /**
     * Serve version.json dengan no-cache headers (selalu fresh dari origin)
     * APK check ini setiap 60-90 detik untuk detect perubahan
     */
    public function getVersion(Request $request): Response
    {
        $payload = $this->getVersionPayloadFromHistory($request);
        $source = 'history';

        if ($payload === null) {
            $payload = $this->fetchVersionPayloadViaAppLb($request);
            $source = 'app_lb';
        }

        if ($payload === null) {
            $payload = $this->syncVersionFileWithConfig($request);
            $source = 'file';
        }

        if ($payload === null) {
            return response()->json(['error' => 'Version file not found'], 404);
        }

        if (is_array($payload)) {
            Log::debug('Serving version payload.', ['source' => $source, 'config_version' => (string) Arr::get($payload, 'config_version', 'unknown')]);
            $configVersion = (string) Arr::get($payload, 'config_version', '1.0.0');
            $configBase = $this->resolveConfigBasePath($request);
            $payload['config_url'] = $request->getSchemeAndHttpHost() . $configBase . '/config.json';
            $payload['config_url_versioned'] = $request->getSchemeAndHttpHost() . $configBase . '/config.json?v=' . rawurlencode($configVersion);
            $content = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        
        return response($content, 200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Cache-Control', 'no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Thu, 01 Jan 1970 00:00:01 GMT')
            ->header('ETag', '"' . md5($content) . '"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
    }

    /**
        * Serve config.json untuk Exambro sync flow.
        * - URL versioned (?v=...) => cache panjang
        * - URL tanpa version   => no-cache (lebih aman untuk fallback client)
     */
    public function getConfig(Request $request): Response
    {
        $payload = null;
        $source = 'history';

        if ($request->is('assets/app/config.json') && $request->query('apikey') !== null) {
            $payload = $this->fetchConfigPayloadViaAppLb($request, '/' . ltrim($request->path(), '/'));
            $source = 'app_lb';
        }

        if ($payload === null) {
            $payload = $this->getConfigPayloadFromHistory();
        }

        if ($payload === null) {
            $payload = $this->fetchConfigPayloadViaAppLb($request);
            $source = 'app_lb';
        }

        if ($payload === null) {
            $configFile = public_path('api/config.json');

            if (! file_exists($configFile)) {
                return response()->json(['error' => 'Config file not found'], 404);
            }

            $content = file_get_contents($configFile);
            $payload = json_decode($content, true);
            $source = 'file';
        }

        if (is_array($payload)) {
            Log::debug('Serving config payload.', ['source' => $source, 'payload_size' => strlen(json_encode($payload))]);
            $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
            $payload['base_url'] = $baseUrl;
            $payload['exambro_page_url'] = $baseUrl . '/exambro';
            $payload['load_balancing_url'] = $baseUrl . '/go-cbt';
            $payload['mirror_list_url'] = $baseUrl . '/api/mirror_list.json';
            $content = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $etag = '"' . md5($content) . '"';

        // Check If-None-Match (client cache validation)
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag);
        }

        $requestedVersion = trim((string) $request->query('v', ''));
        $cacheControl = $requestedVersion !== ''
            ? 'public, max-age=31536000, immutable'
            : 'no-cache, must-revalidate, max-age=0';

        return response($content, 200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Cache-Control', $cacheControl)
            ->header('ETag', $etag)
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
    }

    /**
     * ADMIN: Update version untuk trigger refresh di semua APK
     * POST /api/config/update dengan body JSON
     */
    public function updateConfig(Request $request)
    {
        // TODO: Add authentication/authorization
        // Misal middleware 'auth:api' atau custom token check

        $versionFile = public_path('api/version.json');
        $configFile = public_path('api/config.json');

        try {
            // Update config.json
            if ($request->has('config')) {
                $configData = $request->input('config');
                file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            // Update version.json
            $newVersion = $request->input('version', date('Y-m-d\TH:i:s\Z'));
            $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
            $versionData = [
                'config_version' => $newVersion,
                'config_url' => $baseUrl . '/api/config.json',
                'config_url_versioned' => $baseUrl . '/api/config.json?v=' . rawurlencode($newVersion),
                'last_updated' => now()->toIso8601String(),
                'timestamp' => now()->timestamp * 1000,
                'min_app_version' => $request->input('min_app_version', '1.0.0'),
                'message' => $request->input('message', 'Configuration updated'),
            ];
            
            file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return response()->json([
                'success' => true,
                'message' => 'Configuration updated',
                'new_version' => $newVersion,
                'affected_devices' => 'All devices will detect change within 90 seconds',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check endpoint (APK dapat check apakah server available)
     */
    public function health(Request $request): Response
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'server' => $request->getHost(),
        ])
        ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    private function resolveConfigBasePath(Request $request): string
    {
        $path = '/' . ltrim((string) $request->path(), '/');

        if (str_starts_with($path, '/assets/app')) {
            return '/assets/app';
        }

        return '/api';
    }

    private function fetchVersionPayloadViaAppLb(Request $request): ?array
    {
        $cacheKey = 'config_api_lb:version_payload';

        return Cache::remember($cacheKey, now()->addSeconds(5), function () use ($request) {
            $nodeBase = $this->selectNextNodeBaseUrl($request);
            if ($nodeBase === null) {
                return null;
            }

            try {
                $response = Http::connectTimeout(1)
                    ->timeout(2)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get(rtrim($nodeBase, '/') . '/api/version.json', [
                        '_lb' => '1',
                        '_t' => now()->timestamp,
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                $payload = $response->json();

                return is_array($payload) ? $payload : null;
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch version via app LB.', [
                    'node' => $nodeBase,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    private function fetchConfigPayloadViaAppLb(Request $request, string $path = '/api/config.json'): ?array
    {
        $versionTag = trim((string) $request->query('v', 'no-version'));
        $cacheKey = 'config_api_lb:config_payload:' . sha1($versionTag . '|' . $path);

        return Cache::remember($cacheKey, now()->addSeconds(15), function () use ($request, $path) {
            $nodeBase = $this->selectNextNodeBaseUrl($request);
            if ($nodeBase === null) {
                return null;
            }

            try {
                $query = ['_lb' => '1'];
                $requestedVersion = trim((string) $request->query('v', ''));
                if ($requestedVersion !== '') {
                    $query['v'] = $requestedVersion;
                }
                if ($request->query('apikey') !== null) {
                    $query['apikey'] = trim((string) $request->query('apikey'));
                }

                $url = rtrim($nodeBase, '/') . '/' . ltrim($path, '/');
                $response = Http::connectTimeout(1)
                    ->timeout(2)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get($url, $query);

                if (! $response->successful()) {
                    return null;
                }

                $payload = $response->json();

                return is_array($payload) ? $payload : null;
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch config via app LB.', [
                    'node' => $nodeBase,
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    private function getConfigPayloadFromHistory(): ?array
    {
        $cacheKey = 'config_api:json_history:config_payload';
        $ttl = now()->addSeconds(random_int(5, 60));

        if (Cache::has($cacheKey)) {
            Log::debug('Config payload cache hit for cbt_json_history.', ['cache_key' => $cacheKey]);
        }

        return Cache::remember($cacheKey, $ttl, function () use ($cacheKey) {
            Log::debug('Config payload cache miss for cbt_json_history.', ['cache_key' => $cacheKey]);

            try {
                $row = DB::table('cbt_json_history')
                    ->where('file_name', 'config.json')
                    ->orderByDesc('created_at')
                    ->first(['content']);
            } catch (\Throwable $e) {
                Log::warning('Failed to read config payload from cbt_json_history.', [
                    'message' => $e->getMessage(),
                ]);

                return null;
            }

            if (! $row || ! is_string($row->content)) {
                Log::debug('No valid config.json row found in cbt_json_history.', ['cache_key' => $cacheKey]);
                return null;
            }

            $payload = json_decode($row->content, true);
            if (! is_array($payload)) {
                Log::debug('Config payload from cbt_json_history is not valid JSON.', ['cache_key' => $cacheKey]);
                return null;
            }

            Log::debug('Loaded config payload from cbt_json_history into Redis cache.', ['cache_key' => $cacheKey]);
            return $payload;
        });
    }

    private function getVersionPayloadFromHistory(Request $request): ?array
    {
        $cacheKey = 'config_api:json_history:version_payload';
        $ttl = now()->addSeconds(random_int(5, 60));

        if (Cache::has($cacheKey)) {
            Log::debug('Version payload cache hit for cbt_json_history.', ['cache_key' => $cacheKey]);
        }

        return Cache::remember($cacheKey, $ttl, function () use ($request, $cacheKey) {
            Log::debug('Version payload cache miss for cbt_json_history.', ['cache_key' => $cacheKey]);

            $configPayload = $this->getConfigPayloadFromHistory();
            if ($configPayload === null) {
                Log::debug('Config payload empty when building version payload from history.', ['cache_key' => $cacheKey]);
                return null;
            }

            $configVersion = trim((string) ($configPayload['current_version'] ?? $configPayload['version'] ?? ''));
            if ($configVersion === '') {
                Log::debug('Config version missing in history payload.', ['cache_key' => $cacheKey]);
                return null;
            }

            $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
            $payload = [
                'config_version' => $configVersion,
                'config_url' => $baseUrl . '/api/config.json',
                'config_url_versioned' => $baseUrl . '/api/config.json?v=' . rawurlencode($configVersion),
                'last_updated' => (string) ($configPayload['last_updated'] ?? now()->toIso8601String()),
                'timestamp' => now()->timestamp * 1000,
                'min_app_version' => (string) ($configPayload['min_app_version'] ?? '1.0.0'),
                'message' => (string) ($configPayload['message'] ?? 'Configuration updated'),
            ];

            Log::debug('Loaded version payload from cbt_json_history into Redis cache.', ['cache_key' => $cacheKey, 'config_version' => $configVersion]);
            return $payload;
        });
    }

    private function syncVersionFileWithConfig(Request $request): ?array
    {
        $configFile = public_path('api/config.json');
        $versionFile = public_path('api/version.json');

        if (! file_exists($configFile)) {
            return null;
        }

        $configPayload = json_decode(file_get_contents($configFile), true);
        if (! is_array($configPayload)) {
            return null;
        }

        $existingVersionPayload = null;
        if (file_exists($versionFile)) {
            $existingVersionPayload = json_decode(file_get_contents($versionFile), true);
        }

        $effectiveVersion = (string) now()->timestamp;

        if (is_array($existingVersionPayload)) {
            if (Arr::get($existingVersionPayload, 'config_url', '') === '/api/config.json'
                && Arr::get($existingVersionPayload, 'config_url_versioned', '') === '/api/config.json?v=' . rawurlencode($effectiveVersion)
            ) {
                return $existingVersionPayload;
            }
        }

        $payload = [
            'config_version' => $effectiveVersion,
            'config_url' => '/api/config.json',
            'config_url_versioned' => '/api/config.json?v=' . rawurlencode($effectiveVersion),
            'last_updated' => now()->toIso8601String(),
            'timestamp' => now()->timestamp * 1000,
            'min_app_version' => Arr::get($existingVersionPayload, 'min_app_version', '1.0.0'),
            'message' => Arr::get($existingVersionPayload, 'message', 'Configuration updated'),
        ];

        file_put_contents($versionFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $payload;
    }

    private function selectNextNodeBaseUrl(Request $request): ?string
    {
        if ($request->query('_lb') === '1') {
            return null;
        }

        $nodes = $this->configuredLbNodes($request);
        if (count($nodes) === 0) {
            return null;
        }

        $indexKey = 'config_api_lb:round_robin_index';
        $index = (int) Cache::get($indexKey, -1);
        $next = ($index + 1) % count($nodes);
        Cache::put($indexKey, $next, now()->addHours(12));

        return $nodes[$next] ?? null;
    }

    private function configuredLbNodes(Request $request): array
    {
        $cacheKey = 'config_api_lb:node_list';

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($request) {
            try {
                $row = DB::table('web_settings')
                    ->where('setting_key', 'cbt_servers_list')
                    ->first(['setting_value']);
            } catch (\Throwable $e) {
                return [];
            }

            if (! $row || ! isset($row->setting_value)) {
                return [];
            }

            $decoded = json_decode((string) $row->setting_value, true);
            if (! is_array($decoded)) {
                return [];
            }

            $currentHost = strtolower((string) $request->getHost());
            $nodes = [];

            foreach ($decoded as $server) {
                if (! is_array($server)) {
                    continue;
                }

                $url = trim((string) ($server['url'] ?? ''));
                if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                if (((bool) ($server['hidden'] ?? false)) === true) {
                    continue;
                }

                if (((bool) ($server['lb_enabled'] ?? true)) !== true) {
                    continue;
                }

                $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                if ($host === '' || $host === $currentHost) {
                    continue;
                }

                $nodes[$host] = rtrim($url, '/');
            }

            return array_values($nodes);
        });
    }
}
