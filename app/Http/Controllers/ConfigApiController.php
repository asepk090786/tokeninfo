<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class ConfigApiController extends Controller
{
    /**
     * Serve version.json dengan no-cache headers (selalu fresh dari origin)
     * APK check ini setiap 60-90 detik untuk detect perubahan
     */
    public function getVersion(Request $request): Response
    {
        $versionFile = public_path('api/version.json');
        
        if (!file_exists($versionFile)) {
            return response()->json(['error' => 'Version file not found'], 404);
        }

        $content = file_get_contents($versionFile);
        $payload = json_decode($content, true);

        if (is_array($payload)) {
            $configVersion = (string) Arr::get($payload, 'config_version', '1.0.0');
            $payload['config_url'] = $request->getSchemeAndHttpHost() . '/api/config.json';
            $payload['config_url_versioned'] = $request->getSchemeAndHttpHost() . '/api/config.json?v=' . rawurlencode($configVersion);
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
        $configFile = public_path('api/config.json');
        
        if (!file_exists($configFile)) {
            return response()->json(['error' => 'Config file not found'], 404);
        }

        $content = file_get_contents($configFile);
        $payload = json_decode($content, true);

        if (is_array($payload)) {
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
}
