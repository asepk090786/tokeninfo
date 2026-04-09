<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ExambroApiKey
{
    private const APP_NAME_CACHE_KEY = 'exambro_app_name';
    private const APP_NAME_CACHE_TTL_SECONDS = 120;

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders($this->corsHeaders($request));
        }

        // 1. Admin session aktif → langsung diizinkan
        if ($request->session()->get('cbt_admin_auth') === true) {
            return $next($request);
        }

        if ($this->isBlockedBenchmarkUserAgent($request->userAgent())) {
            $this->logRejectedRequest($request, 403, 'blocked_benchmark_user_agent');

            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden user agent.',
            ], 403)->withHeaders($this->corsHeaders($request));
        }

        // 1.5 User-Agent Exambro -> diizinkan untuk akses halaman/API Exambro.
        if ($this->matchesExambroUserAgent($request->userAgent())) {
            return $next($request);
        }

        // 1.6 Fallback aman untuk navigasi PWA Exambro yang kadang memakai UA WebView biasa.
        if ($this->isTrustedExambroPwaNavigation($request)) {
            return $next($request);
        }

        // 2. Selainnya → 401 Unauthorized
        $appName = (string) Cache::remember(
            self::APP_NAME_CACHE_KEY,
            now()->addSeconds(self::APP_NAME_CACHE_TTL_SECONDS),
            function () {
                return (string) (DB::table('setting')->where('id_setting', 1)->value('nama_aplikasi') ?? 'GARUDA CBT');
            }
        );

        $this->logRejectedRequest($request, 401, 'user_agent_not_allowed');

        $payload = [
            'status'  => 'error',
            'message' => 'Unauthorized. Akses hanya untuk aplikasi Exambro yang valid.',
            'app_name' => $appName,
            'application_name' => $appName,
            'nama_aplikasi' => $appName,
        ];

        if ($this->shouldRenderHtmlUnauthorized($request)) {
            return response()
                ->view('cbt-info.exambro-unauthorized', [
                    'appName' => $appName,
                    'message' => $payload['message'],
                ], 401)
                ->withHeaders($this->corsHeaders($request));
        }

        return response()->json($payload, 401)->withHeaders($this->corsHeaders($request));
    }

    private function shouldRenderHtmlUnauthorized(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return false;
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        return str_contains($accept, 'text/html') || $accept === '' || str_contains($accept, '*/*');
    }

    private function corsHeaders(Request $request): array
    {
        return [
            'Access-Control-Allow-Origin' => $this->allowedCorsOrigin($request),
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin',
        ];
    }

    private function isBlockedBenchmarkUserAgent(?string $userAgent): bool
    {
        $ua = strtolower(trim((string) $userAgent));
        if ($ua === '') {
            return false;
        }

        return str_contains($ua, 'apachebench') || str_contains($ua, 'wrk/');
    }

    private function allowedCorsOrigin(Request $request): string
    {
        $origin = (string) $request->headers->get('Origin', '');
        if ($origin !== '') {
            $originHost = parse_url($origin, PHP_URL_HOST);
            if (is_string($originHost) && strcasecmp($originHost, $request->getHost()) === 0) {
                return $origin;
            }
        }

        return $request->getSchemeAndHttpHost();
    }

    private function matchesExambroUserAgent(?string $userAgent): bool
    {
        if (! $this->isUserAgentDetectionEnabled()) {
            return false;
        }

        $ua = strtolower(trim((string) $userAgent));
        if ($ua === '') {
            return false;
        }

        foreach ($this->getUserAgentPatterns() as $pattern) {
            if ($pattern !== '' && str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isUserAgentDetectionEnabled(): bool
    {
        $raw = $this->readPersistedSetting('exambro_user_agent_detection_enabled', 1);

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }

        if (is_string($raw)) {
            return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on', 'active', 'aktif'], true);
        }

        return true;
    }

    private function getUserAgentPatterns(): array
    {
        $stored = (string) $this->readPersistedSetting('exambro_user_agent_patterns', "exambro\nsafeexambrowser\nseb");
        $parts = preg_split('/[\r\n,]+/', $stored) ?: [];
        $normalized = [];

        foreach ($parts as $part) {
            $value = strtolower(trim((string) $part));
            if ($value !== '') {
                $normalized[$value] = $value;
            }
        }

        if (count($normalized) === 0) {
            return ['exambro', 'safeexambrowser', 'seb'];
        }

        return array_values($normalized);
    }

    private function ensureWebSettingsTable(): bool
    {
        try {
            if (Schema::hasTable('web_settings')) {
                return true;
            }
        } catch (\Throwable $e) {
            // Continue with best effort create.
        }

        try {
            DB::statement("CREATE TABLE IF NOT EXISTS web_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(120) NOT NULL UNIQUE,
                setting_value LONGTEXT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX web_settings_updated_at_idx (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            return Schema::hasTable('web_settings');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function readPersistedSetting(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'web_setting:' . $key;
        $cached = Cache::get($cacheKey, '__missing__');

        if ($cached !== '__missing__') {
            return $cached;
        }

        // Backward compatibility with legacy cache key.
        $legacyCached = Cache::get($key, '__missing__');
        if ($legacyCached !== '__missing__') {
            Cache::forever($cacheKey, $legacyCached);

            return $legacyCached;
        }

        if (! $this->ensureWebSettingsTable()) {
            return $default;
        }

        try {
            $row = DB::table('web_settings')
                ->where('setting_key', $key)
                ->first(['setting_value']);
        } catch (\Throwable $e) {
            return $default;
        }

        if (! $row || ! isset($row->setting_value)) {
            return $default;
        }

        $decoded = json_decode((string) $row->setting_value, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $row->setting_value;

        Cache::forever($cacheKey, $value);

        return $value;
    }

    private function logRejectedRequest(Request $request, int $statusCode, string $reason): void
    {
        try {
            Log::warning('Exambro middleware rejected request', [
                'status_code' => $statusCode,
                'reason' => $reason,
                'path' => $request->path(),
                'full_url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'referer' => (string) $request->headers->get('referer', ''),
            ]);
        } catch (\Throwable $e) {
            // Never break request flow on logging failure.
        }
    }

    private function isTrustedExambroPwaNavigation(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->path() !== 'exambro') {
            return false;
        }

        $source = strtolower(trim((string) $request->query('source', '')));
        if ($source !== 'pwa') {
            return false;
        }

        $referer = trim((string) $request->headers->get('referer', ''));
        if ($referer === '') {
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        if (! is_string($refererHost) || strcasecmp($refererHost, $request->getHost()) !== 0) {
            return false;
        }

        $refererPath = (string) parse_url($referer, PHP_URL_PATH);

        return str_ends_with($refererPath, '/exambro-sw.js');
    }

}
