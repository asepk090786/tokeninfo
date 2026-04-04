<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExambroApiKey
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin, X-Exambro-Key',
            ]);
        }

        // 1. Admin session aktif → langsung diizinkan
        if ($request->session()->get('cbt_admin_auth') === true) {
            return $next($request);
        }

        // 1.5 User-Agent Exambro -> diizinkan untuk akses halaman/API Exambro.
        if ($this->matchesExambroUserAgent($request->userAgent())) {
            return $next($request);
        }

        $validKey = $this->getActiveExambroApiKey();

        // Ambil key dari header, query, atau cookie agar PWA tetap auto-authorize.
        $provided = trim((string) (
            $request->header('X-Exambro-Key')
            ?? $request->query('key')
            ?? $request->cookie('exambro_key', '')
        ));

        $keyValid = $validKey !== '' && hash_equals($validKey, $provided);

        // 2. API key cocok → diizinkan
        if ($keyValid) {
            $response = $next($request);

            // Persist key ke cookie saat valid agar akses PWA berikutnya tidak perlu query key.
            if ($provided !== '' && $request->cookie('exambro_key') !== $provided) {
                $response->headers->setCookie(cookie(
                    'exambro_key',
                    $provided,
                    60 * 24 * 30,
                    '/',
                    null,
                    $request->isSecure(),
                    true,
                    false,
                    'Lax'
                ));
            }

            return $response;
        }

        // 3. Selainnya → 401 Unauthorized
        $appName = (string) (DB::table('setting')->where('id_setting', 1)->value('nama_aplikasi') ?? 'GARUDA CBT');

        return response()->json([
            'status'  => 'error',
            'message' => 'Unauthorized. API key tidak valid atau tidak disertakan.',
            'app_name' => $appName,
            'application_name' => $appName,
            'nama_aplikasi' => $appName,
        ], 401)->withHeaders([
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin, X-Exambro-Key',
        ]);
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

    private function getActiveExambroApiKey(): string
    {
        $cachedKey = (string) Cache::get('exambro_api_key', '');
        if ($cachedKey !== '') {
            return $cachedKey;
        }

        $fileKey = $this->readExambroApiKeyFromFile();
        if ($fileKey !== '') {
            Cache::forever('exambro_api_key', $fileKey);

            return $fileKey;
        }

        return (string) config('app.exambro_api_key', '');
    }

    private function exambroApiKeyFilePath(): string
    {
        return storage_path('app/private/exambro-api-key.json');
    }

    private function readExambroApiKeyFromFile(): string
    {
        $path = $this->exambroApiKeyFilePath();

        if (! File::exists($path)) {
            return '';
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return '';
        }

        return trim((string) ($decoded['api_key'] ?? ''));
    }
}
