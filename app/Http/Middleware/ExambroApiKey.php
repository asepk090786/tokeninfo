<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden user agent.',
            ], 403)->withHeaders($this->corsHeaders($request));
        }

        // 1.5 User-Agent Exambro -> diizinkan untuk akses halaman/API Exambro.
        if ($this->matchesExambroUserAgent($request->userAgent())) {
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

        return response()->json([
            'status'  => 'error',
            'message' => 'Unauthorized. Akses hanya untuk aplikasi Exambro yang valid.',
            'app_name' => $appName,
            'application_name' => $appName,
            'nama_aplikasi' => $appName,
        ], 401)->withHeaders($this->corsHeaders($request));
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
        $raw = Cache::get('exambro_user_agent_detection_enabled', 1);

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
        $stored = (string) Cache::get('exambro_user_agent_patterns', 'exambro');
        $parts = preg_split('/[\r\n,]+/', $stored) ?: [];
        $normalized = [];

        foreach ($parts as $part) {
            $value = strtolower(trim((string) $part));
            if ($value !== '') {
                $normalized[$value] = $value;
            }
        }

        if (count($normalized) === 0) {
            return ['exambro'];
        }

        return array_values($normalized);
    }

}
