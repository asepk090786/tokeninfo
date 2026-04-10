<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ZipArchive;

class CbtInfoController extends Controller
{
    private const SERVER_LOGIN_COUNTER_PREFIX = 'server_login_count:';
    private const SERVER_ACTIVE_MEMBER_PREFIX = 'server_active_member:';
    private const SERVER_ACTIVE_INDEX_PREFIX = 'server_active_index:';
    private const SERVER_ACTIVE_USER_TTL_SECONDS = 120;
    private const SERVER_LOGIN_COUNTER_TTL_SECONDS = 7200;
    private const DB_REFRESH_INTERVAL_SECONDS = 300;
    private const SERVER_STATUS_UP_TTL_SECONDS = 30;
    private const SERVER_STATUS_DOWN_TTL_SECONDS = 10;
    private const SERVER_STATUS_KNOWN_TTL_SECONDS = 1800;
    private const SERVER_STATUS_LOCK_SECONDS = 5;
    private const CACHE_KEY_CBT_INFO = 'cbt_info_payload';
    private const CACHE_KEY_CBT_TOKEN_ROW = 'cbt_token_row:id:1';
    private const CACHE_KEY_SETTING_ROW = 'setting_row:id:1';
    private const CACHE_KEY_PIN_EXAMBRO_ROW = 'pin_exambro_row:id:1';

    public function loadBalancer(Request $request)
    {
        $info = $this->getInfoFromGarudaCbt();
        $servers = $this->buildServerList($info);
        $selectedServer = $this->selectAvailableServer($servers);
        $targetUrl = trim((string) ($selectedServer['url'] ?? ''));

        if ($targetUrl === '' || ! filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return redirect()->route('cbt.index');
        }

        $targetHost = parse_url($targetUrl, PHP_URL_HOST);
        if (is_string($targetHost) && strcasecmp($targetHost, $request->getHost()) === 0) {
            return redirect()->route('cbt.index');
        }

        $selectedServerKey = trim((string) ($selectedServer['key'] ?? ''));
        if ($selectedServerKey !== '') {
            $this->increaseServerLoginCount($selectedServerKey);
            $this->touchServerActivePresence($selectedServerKey, $request);
        }

        return redirect()->away($targetUrl);
    }

    public function connectServer(Request $request, string $serverKey)
    {
        $info = $this->getInfoFromGarudaCbt();
        $serverMap = collect($this->buildServerList($info))->keyBy('key');
        $server = $serverMap->get($serverKey);

        if (! is_array($server) || empty($server['url'])) {
            return redirect()->route('cbt.exambro.page');
        }

        $targetUrl = trim((string) ($server['url'] ?? ''));
        if ($targetUrl === '' || ! filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return redirect()->route('cbt.exambro.page');
        }

        $targetHost = parse_url($targetUrl, PHP_URL_HOST);
        if (is_string($targetHost) && strcasecmp($targetHost, $request->getHost()) === 0) {
            return redirect()->route('cbt.exambro.page');
        }

        if ((($server['selection_runtime_enabled'] ?? true) !== true)) {
            return redirect()->route('cbt.exambro.page');
        }

        if ($this->isServerHighLoad($server)) {
            return redirect()->route('cbt.lb');
        }

        $this->increaseServerLoginCount((string) $server['key']);
        $this->touchServerActivePresence((string) $server['key'], $request);

        return redirect()->away($targetUrl);
    }

    public function index(Request $request)
    {
        // Version sync feature disabled — no automatic remote version checks

        if ($this->isExambroClient($request)) {
            return redirect()->route('cbt.exambro.page');
        }

        $info = $this->getInfoFromGarudaCbt();
        $exambroActive = $this->isExambroActive();
        $exambroTokenSource = $this->getExambroTokenSource();
        $pinActive = $this->isExambroPinActive();
        $currentPinExambro = (string) ($this->getPinExambroRow()?->pin ?? '');
        $servers = $this->attachCachedQrCodes($this->buildServerList($info));

        return view('cbt-info.index', compact('info', 'exambroActive', 'exambroTokenSource', 'pinActive', 'currentPinExambro', 'servers'));
    }

    public function exambroPage()
    {
        $info = $this->getInfoFromGarudaCbt();

        return view('cbt-info.exambro', [
            'schoolName' => $info->school,
            'appName' => $info->app_name,
            'canTogglePinVisibility' => session('cbt_admin_auth') === true,
            'exambroTokenVisibleOnPage' => $this->isExambroTokenVisibleOnPage(),
            'exambroPinActive' => $this->isExambroPinActive(),
        ]);
    }

    public function tokenInfo()
    {
        // Do not call remote token/version endpoints from the public API.
        // Return DB-driven values only to avoid external network calls.
        $info = $this->getInfoFromGarudaCbt();
        $exambroActive = $this->isExambroActive();
        $pinActive = $this->isExambroPinActive();
        $servers = $this->buildServerList($info);

        $cbtToken = $this->readCbtTokenDirect();

        return $this->apiJson([
            'token' => $cbtToken,
            'cbt_token' => $cbtToken,
            'exambro_token' => $info->exambro_token,
            'exambro_active' => $exambroActive,
            'status_pin' => $pinActive ? 1 : 0,
            'status_pin_label' => $pinActive ? 'ACTIVE' : 'INACTIVE',
            'statusPin' => $pinActive ? 1 : 0,
            'statusPIN' => $pinActive ? 1 : 0,
            'school' => $info->school,
            'app_name' => $info->app_name,
            'application_name' => $info->app_name,
            'nama_aplikasi' => $info->app_name,
            'token_updated_at' => $info->token_updated_at,
            'token_valid_until' => $info->token_valid_until,
            'description' => $info->description,
            'servers' => $servers,
        ]);
    }

    /**
     * Check version token at most every 120 seconds; if remote version changed,
     * fetch token.json and update local token and PIN accordingly.
     */
    private function ensureTokenSyncedFromVersion(): void
    {
        $lastCheckedKey = 'cbt_token_version_last_checked';
        $lastChecked = Cache::get($lastCheckedKey);
        $now = time();

        if ($lastChecked && (is_int($lastChecked) && ($now - $lastChecked) < 120)) {
            return; // checked recently
        }

        $lb = (string) $this->readPersistedSetting('cbt_load_balancing_url', $this->homepageUrl('/go-cbt'));
        $lb = rtrim($lb, '/');
        $tokenVersionEndpoint = (string) $this->readPersistedSetting('cbt_token_version_endpoint', '/version.token.json');
        $tokenEndpoint = (string) $this->readPersistedSetting('cbt_token_endpoint', '/token.json');
        $apiKey = (string) $this->readPersistedSetting('cbt_exambro_api_key', '');

        $build = function (string $base, string $path) use ($apiKey) {
            $path = trim($path);
            if ($path === '') return '';
            if (preg_match('#^https?://#i', $path)) {
                if (str_contains($path, '{api_key}')) {
                    return str_replace('{api_key}', rawurlencode($apiKey), $path);
                }
                $sep = str_contains($path, '?') ? '&' : '?';
                return $path . $sep . 'api_key=' . rawurlencode($apiKey);
            }
            $base = rtrim($base, '/');
            $path = '/' . ltrim($path, '/');
            $url = $base . $path;
            if (str_contains($path, '{api_key}')) {
                return str_replace('{api_key}', rawurlencode($apiKey), $url);
            }
            $sep = str_contains($url, '?') ? '&' : '?';
            return $url . $sep . 'api_key=' . rawurlencode($apiKey);
        };

        $versionUrl = $build($lb, $tokenVersionEndpoint);
        if ($versionUrl === '') {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        try {
            $res = Http::connectTimeout(3)->timeout(5)->acceptJson()->get($versionUrl);
        } catch (\Throwable $e) {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        if (! $res->successful()) {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        $json = null;
        try {
            $json = $res->json();
        } catch (\Throwable $e) {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        if (! is_array($json)) {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        $remoteVersion = (string) ($json['version'] ?? $json['config_version'] ?? '');
        if ($remoteVersion === '') {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        $cachedVersion = Cache::get('cbt_remote_version');

        if ($cachedVersion === $remoteVersion) {
            Cache::put($lastCheckedKey, $now, 120);
            return; // no change
        }

        // Version changed — fetch token.json
        $tokenUrl = $build($lb, $tokenEndpoint);
        if ($tokenUrl === '') {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        try {
            $tres = Http::connectTimeout(3)->timeout(5)->acceptJson()->get($tokenUrl);
        } catch (\Throwable $e) {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        if (! $tres->successful()) {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        $tjson = null;
        try {
            $tjson = $tres->json();
        } catch (\Throwable $e) {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        if (! is_array($tjson)) {
            Cache::put($lastCheckedKey, $now, 120);
            return;
        }

        $fetchedToken = (string) ($tjson['token'] ?? $tjson['cbt_token'] ?? $tjson['exambro_token'] ?? '');
        $fetchedPin = isset($tjson['pin']) ? strtoupper(trim((string)$tjson['pin'])) : null;

        if ($fetchedToken !== '') {
            DB::table('cbt_token')->updateOrInsert(
                ['id_token' => 1],
                [
                    'token' => strtoupper(trim($fetchedToken)),
                    'updated' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now(),
                ]
            );
            Cache::forget(self::CACHE_KEY_CBT_TOKEN_ROW);
        }

            if ($fetchedPin !== null && $fetchedPin !== '') {
            DB::table('pin_exambro')->updateOrInsert(
                ['id' => 1],
                [
                    'pin' => $fetchedPin,
                    'updated' => now()->format('Y-m-d H:i:s'),
                ]
            );
            Cache::forget(self::CACHE_KEY_PIN_EXAMBRO_ROW);
            // deprecated: no longer writing to public/pin.json here
        }

        // Update cached remote version and mark last checked
        Cache::put('cbt_remote_version', $remoteVersion);
        Cache::put($lastCheckedKey, $now, 120);
        Cache::forget(self::CACHE_KEY_CBT_INFO);
    }

    /**
     * Return current CBT token (from current info payload).
            if ($request->has('server_name_primary')) {
                $this->writePersistedSetting('cbt_server_name_primary', trim((string) ($validated['server_name_primary'] ?? '')) ?: 'Server Utama');
            }

            'status' => 'ok',
            'token' => $info->cbt_token ?? $info->token ?? null,
            'token_updated_at' => $info->token_updated_at ?? null,
            'token_valid_until' => $info->token_valid_until ?? null,
        ]);
    }

    /**
     * Reset CBT token (admin only) and refresh cache.
     */
    public function resetCbtToken(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return $this->apiJson(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $newToken = strtoupper(Str::random(6));

        $cbt = \App\Models\CbtInfo::first();
        if ($cbt) {
            $cbt->token = $newToken;
            $cbt->token_updated_at = now()->toIso8601String();
            // keep existing validity or set default 90 minutes
            $cbt->token_valid_until = now()->addMinutes(90)->toIso8601String();
            $cbt->save();
        } else {
            // ensure at least one row exists
            \App\Models\CbtInfo::create([
                'token' => $newToken,
                'cbt_url' => '',
                'description' => '',
            ]);
        }

        // Clear cached payloads so other endpoints show the new token
        Cache::forget(self::CACHE_KEY_CBT_INFO);
        Cache::forget(self::CACHE_KEY_CBT_TOKEN_ROW);

        // Log for audit
        Log::info('CBT token reset by admin', ['new_token' => $newToken, 'ip' => request()->ip()]);

        return $this->apiJson(['status' => 'ok', 'new_token' => $newToken]);
    }

    public function mirrorList()
    {
        $servers = $this->getConfiguredServers();
        if ($servers === []) {
            $servers = $this->legacyServerDefinitions($this->getInfoFromGarudaCbt());
        }

        $payload = $this->buildMirrorListPayload($servers);

        return response()
            ->json($payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function exambroInfo(Request $request)
    {
        // Handle CORS preflight
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders($this->corsHeaders($request));
        }

        $info           = $this->getInfoFromGarudaCbt();
        $exambroActive  = $this->isExambroActive();
        $warningValue   = $this->getExambroWarningValue();
        $servers        = $this->buildServerList($info);
        $serverMap      = collect($servers)->keyBy('key');
        $recommended    = collect($servers)->first(function ($server) {
            return (($server['is_up'] ?? false) === true)
                && ! empty($server['url']);
        });

        $primary = $serverMap->get('primary', $servers[0] ?? []);
        $backup1 = $serverMap->get('backup1', $servers[1] ?? []);
        $backup2 = $serverMap->get('backup2', $servers[2] ?? []);
        $tokenStatusCode = $exambroActive ? 1 : 0;
        $tokenStatusLabel = $exambroActive ? 'ACTIVE' : 'INACTIVE';
        $pinStatusCode = $this->isExambroPinActive() ? 1 : 0;
        $pinStatusLabel = $pinStatusCode === 1 ? 'ACTIVE' : 'INACTIVE';
        $warningStatusCode = $warningValue === 1 ? 1 : 0;
        $warningStatusLabel = $warningStatusCode === 1 ? 'ON' : 'OFF';
        $exambroToken = $info->exambro_token;
        $showExambroTokenOnPage = $this->isExambroTokenVisibleOnPage();
        $exambroTokenForExambroPage = $showExambroTokenOnPage ? $exambroToken : null;

        return $this->apiJson([
            /* ── Informasi Token ────────────────────────────── */
            'status'            => 'ok',
            'token'             => $exambroTokenForExambroPage,
            'exambro_token'     => $exambroTokenForExambroPage,
            'token_soal'        => $this->readCbtTokenDirect(),
            'cbt_token'         => $this->readCbtTokenDirect(),
            'exambro_active'    => $exambroActive,
            'show_exambro_token_on_page' => $showExambroTokenOnPage,
            'token_status'      => strtolower($tokenStatusLabel),
            'warning'           => $warningStatusCode,
            'peringatan'        => $warningStatusCode,

            /* Field status yang mudah diproses aplikasi Exambro */
            'token_active'      => $tokenStatusCode,
            'warning_active'    => $warningStatusCode,
            'status_code'       => [
                'token' => $tokenStatusCode,
                'warning' => $warningStatusCode,
            ],
            'status_label'      => [
                'token' => $tokenStatusLabel,
                'warning' => $warningStatusLabel,
            ],
            'status_flags'      => [
                'token_active' => $tokenStatusCode === 1,
                'warning_active' => $warningStatusCode === 1,
                'pin_active' => $pinStatusCode === 1,
            ],
            'status_pin'        => $pinStatusCode,
            'status_peringatan' => $warningStatusCode,
            'status_pin_label'        => $pinStatusLabel,
            'status_peringatan_label' => $warningStatusLabel,
            // Alias kompatibilitas untuk berbagai parser client
            'statusPIN'         => $pinStatusCode,
            'statusPeringatan'  => $warningStatusCode,
            'statusPin'         => $pinStatusCode,
            'statusWarning'     => $warningStatusCode,
            // Format ringkas yang direkomendasikan untuk parser aplikasi Exambro
            'app_status' => [
                'status_pin' => $pinStatusCode,
                'status_peringatan' => $warningStatusCode,
                'token' => $exambroTokenForExambroPage,
            ],
            'appStatus' => [
                'statusPin' => $pinStatusCode,
                'statusPeringatan' => $warningStatusCode,
                'token' => $exambroTokenForExambroPage,
            ],
            'status_exambro' => [
                'pin' => $pinStatusCode,
                'peringatan' => $warningStatusCode,
                'pin_label' => $pinStatusLabel,
                'peringatan_label' => $warningStatusLabel,
            ],
            'token_updated_at'  => $info->token_updated_at,
            'token_valid_until' => $info->token_valid_until,

            /* ── Informasi Sekolah ──────────────────────────── */
            'school'            => $info->school,
            'app_name'          => $info->app_name,
            'application_name'  => $info->app_name,
            'nama_aplikasi'     => $info->app_name,
            'description'       => $info->description,

            /* ── Server Utama ───────────────────────────────── */
            'server_utama'        => $primary['url'] ?? null,
            'server_utama_status' => ($primary['is_up'] ?? false) ? 'up' : 'down',
            'server_utama_capacity' => (int) ($primary['capacity'] ?? 0),

            /* ── Server Backup 1 ────────────────────────────── */
            'server_backup1'        => $backup1['url'] ?? null,
            'server_backup1_status' => ($backup1['is_up'] ?? false) ? 'up' : 'down',
            'server_backup1_capacity' => (int) ($backup1['capacity'] ?? 0),

            /* ── Server Backup 2 ────────────────────────────── */
            'server_backup2'        => $backup2['url'] ?? null,
            'server_backup2_status' => ($backup2['is_up'] ?? false) ? 'up' : 'down',
            'server_backup2_capacity' => (int) ($backup2['capacity'] ?? 0),

            /* ── Server Rekomendasi (pertama yang UP) ───────── */
            'server_recommended'    => $recommended['url'] ?? null,

            'servers' => collect($servers)->map(function ($server) use ($exambroActive) {
                $selectionRuntimeEnabled = (($server['selection_runtime_enabled'] ?? true) === true);

                return [
                    'key' => $server['key'] ?? null,
                    'name' => $server['name'] ?? null,
                    'url' => $server['url'] ?? null,
                    'status' => ($server['is_up'] ?? false) ? 'up' : 'down',
                    'selectable' => (($server['is_up'] ?? false) === true) && ! empty($server['url']) && $selectionRuntimeEnabled,
                    'country_code' => $server['country_code'] ?? '--',
                    'core' => (int) ($server['core'] ?? 0),
                    'ram' => (string) ($server['ram'] ?? ''),
                    'capacity' => (int) ($server['capacity'] ?? 40),
                    'login_count' => (int) ($server['login_count'] ?? 0),
                    'active_user_count' => (int) ($server['active_user_count'] ?? 0),
                    'active_user_ttl_seconds' => self::SERVER_ACTIVE_USER_TTL_SECONDS,
                    'login_indicator' => $server['login_indicator'] ?? 'low',
                    'login_indicator_label' => $server['login_indicator_label'] ?? 'Rendah',
                    'selection_enabled' => ((bool) ($server['selection_enabled'] ?? true)) === true,
                    'selection_runtime_enabled' => $selectionRuntimeEnabled,
                    'selection_disabled_until' => $server['selection_disabled_until'] ?? null,
                    'selection_timed_disabled' => (($server['selection_timed_disabled'] ?? false) === true),
                ];
            })->values(),

            'checked_at'        => now()->toIso8601String(),
        ]);
    }

    public function heartbeatServerPresence(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders($this->corsHeaders($request));
        }

        $validated = $request->validate([
            'server_key' => ['required', 'string', 'max:120'],
        ]);

        $serverKey = trim((string) ($validated['server_key'] ?? ''));
        $serverExists = collect($this->buildServerList($this->getInfoFromGarudaCbt(), true))
            ->contains(function ($server) use ($serverKey) {
                return (string) ($server['key'] ?? '') === $serverKey;
            });

        if (! $serverExists) {
            return $this->apiJson([
                'status' => 'error',
                'message' => 'Server tidak ditemukan.',
            ]);
        }

        $this->touchServerActivePresence($serverKey, $request);

        return $this->apiJson([
            'status' => 'ok',
            'server_key' => $serverKey,
            'active_user_count' => $this->getServerActiveUserCount($serverKey),
            'active_user_ttl_seconds' => self::SERVER_ACTIVE_USER_TTL_SECONDS,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function exambroTokenStatus(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders($this->corsHeaders($request));
        }

        $tokenActive = $this->isExambroActive() ? 1 : 0;
        $pinActive = $this->isExambroPinActive() ? 1 : 0;
        $exambroToken = $this->getExambroToken();
        $info = $this->getInfoFromGarudaCbt();

        return $this->apiJson([
            'status' => 'ok',
            'status_pin' => $pinActive,
            'status_pin_label' => $pinActive === 1 ? 'ACTIVE' : 'INACTIVE',
            'statusPin' => $pinActive,
            'statusPIN' => $pinActive,
            'token_active' => $tokenActive,
            'token' => $exambroToken,
            'exambro_token' => $exambroToken,
            'school' => $info->school,
            'app_name' => $info->app_name,
            'application_name' => $info->app_name,
            'nama_aplikasi' => $info->app_name,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function exambroWarningStatus(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders($this->corsHeaders($request));
        }

        $warningActive = $this->getExambroWarningValue() === 1 ? 1 : 0;
        $pinActive = $this->isExambroPinActive() ? 1 : 0;
        $info = $this->getInfoFromGarudaCbt();

        return $this->apiJson([
            'status' => 'ok',
            'status_pin' => $pinActive,
            'status_pin_label' => $pinActive === 1 ? 'ACTIVE' : 'INACTIVE',
            'status_peringatan' => $warningActive,
            'status_peringatan_label' => $warningActive === 1 ? 'ON' : 'OFF',
            'school' => $info->school,
            'app_name' => $info->app_name,
            'application_name' => $info->app_name,
            'nama_aplikasi' => $info->app_name,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * New batch endpoint - combines token status + warning status + info
     * Reduces 3 requests to 1 for better performance under load
     */
    public function exambroFullStatus(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders($this->corsHeaders($request));
        }

        $info           = $this->getInfoFromGarudaCbt();
        $exambroActive  = $this->isExambroActive();
        $tokenActive    = $exambroActive ? 1 : 0;
        $pinActive      = $this->isExambroPinActive() ? 1 : 0;
        $warningActive  = $this->getExambroWarningValue() === 1 ? 1 : 0;
        $exambroToken   = $this->getExambroToken();
        $servers        = $this->buildServerList($info);
        $serverMap      = collect($servers)->keyBy('key');
        $recommended    = collect($servers)->first(function ($server) {
            return (($server['is_up'] ?? false) === true)
                && ! empty($server['url']);
        });

        $primary = $serverMap->get('primary', $servers[0] ?? []);
        $backup1 = $serverMap->get('backup1', $servers[1] ?? []);
        $backup2 = $serverMap->get('backup2', $servers[2] ?? []);

        return $this->apiJson([
            'status' => 'ok',
            
            /* ── Token & PIN Status ───────────────────────── */
            'token_active' => $tokenActive,
            'token_active_label' => $tokenActive === 1 ? 'ACTIVE' : 'INACTIVE',
            'status_pin' => $pinActive,
            'status_pin_label' => $pinActive === 1 ? 'ACTIVE' : 'INACTIVE',
            'statusPin' => $pinActive,
            'statusPIN' => $pinActive,
            'token' => $exambroToken,
            'exambro_token' => $exambroToken,
            
            /* ── Warning Status ───────────────────────────── */
            'status_peringatan' => $warningActive,
            'status_peringatan_label' => $warningActive === 1 ? 'ON' : 'OFF',
            'statusPeringatan' => $warningActive,
            'statusWarning' => $warningActive,
            
            /* ── School & App Info ────────────────────────── */
            'school' => $info->school,
            'app_name' => $info->app_name,
            'application_name' => $info->app_name,
            'nama_aplikasi' => $info->app_name,
            'description' => $info->description,
            
            /* ── Server Info ──────────────────────────────── */
            'server_utama' => $primary['url'] ?? null,
            'server_utama_status' => ($primary['is_up'] ?? false) ? 'up' : 'down',
            'server_backup1' => $backup1['url'] ?? null,
            'server_backup1_status' => ($backup1['is_up'] ?? false) ? 'up' : 'down',
            'server_backup2' => $backup2['url'] ?? null,
            'server_backup2_status' => ($backup2['is_up'] ?? false) ? 'up' : 'down',
            'server_recommended' => $recommended['url'] ?? null,
            
            'servers' => collect($servers)->map(function ($server) {
                return [
                    'key' => $server['key'] ?? null,
                    'name' => $server['name'] ?? null,
                    'url' => $server['url'] ?? null,
                    'status' => ($server['is_up'] ?? false) ? 'up' : 'down',
                    'selectable' => (($server['is_up'] ?? false) === true) && ! empty($server['url']),
                ];
            })->values(),
            
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function admin()
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $info = $this->getInfoFromGarudaCbt();
        $servers = $this->buildServerList($info, true);
        $exambroActive = $this->isExambroActive();
        $exambroWarningValue = $this->getExambroWarningValue();
        $exambroTokenVisibleOnPage = $this->isExambroTokenVisibleOnPage();
            $exambroPinActive = $this->isExambroPinActive();
        $admin = (object) [
            'username' => session('cbt_admin_username'),
            'name' => session('cbt_admin_name'),
        ];

        $exambroToken        = $this->getExambroToken();
        $exambroTokenSource  = $this->getExambroTokenSource();
        $exambro_api_key_source = (string) $this->readPersistedSetting('cbt_exambro_api_key_source', 'manual');
        // If admin chooses to take API key from DB, read it from cbt_token.api_key
        if ($exambro_api_key_source === 'db') {
            $exambro_api_key = (string) (DB::table('cbt_token')->where('id_token', 1)->value('api_key') ?? '');
        } else {
            $exambro_api_key = (string) $this->readPersistedSetting('cbt_exambro_api_key', '');
        }
        $exambro_token_endpoint = (string) $this->readPersistedSetting('cbt_token_endpoint', '/token.json');
        $exambro_token_version_endpoint = (string) $this->readPersistedSetting('cbt_token_version_endpoint', '/version.token.json');
        $currentCbtToken = $this->readCbtTokenDirect();
        $currentPinExambro = (string) ($this->getPinExambroRow()?->pin ?? '');
        $exambroEmergencyExitPin = $this->getExambroEmergencyExitPin();
        $exambroEmergencyExitPinSource = $this->hasPersistedExambroEmergencyExitPin() ? 'file' : 'env';
        $userAgentDetectionEnabled = $this->isUserAgentDetectionEnabled();
        $userAgentPatterns = $this->getExambroUserAgentPatternsAsText();
        $loadBalancerMirrorCount = $this->countEligibleLoadBalancerServers($servers);
        $loadBalancerLinkAvailable = $loadBalancerMirrorCount > 1;
        $mirrorListUrl = url('api/mirror_list.json');
        $versionSyncSettings = $this->getVersionSyncSettings();
        $versionSyncServers = $this->getVersionSyncServers();

        // If admin hasn't explicitly configured version sync servers, persist
        // the default list derived from the mirror list so the textarea shows
        // the same ordered list and can be edited going forward.
        $rawVersionSyncStored = $this->readPersistedSetting('version_sync_servers', null);
        if (($rawVersionSyncStored === null || $rawVersionSyncStored === []) && is_array($versionSyncServers) && $versionSyncServers !== []) {
            $this->writePersistedSetting('version_sync_servers', $versionSyncServers);
        }

        $versionSyncTargets = $this->collectVersionSyncTargets($versionSyncServers);
        $versionSyncServersText = $this->exportVersionSyncServersAsText($versionSyncServers);
        $versionData = $this->readVersionPayloadFromFile();
        $currentConfigVersion = trim((string) ($versionData['config_version'] ?? ''));
        // Redis cluster config removed from admin view
        $redisConfig = [];

        return view('cbt-info.admin', compact(
            'info',
            'exambroActive',
            'admin',
            'servers',
            'exambroToken',
            'exambro_api_key' ,
            'exambro_api_key_source',
            'exambro_token_endpoint',
            'exambro_token_version_endpoint',
            'exambroTokenSource',
            'exambroEmergencyExitPin',
            'exambroEmergencyExitPinSource',
            'userAgentDetectionEnabled',
            'userAgentPatterns',
            'exambroWarningValue',
            'exambroTokenVisibleOnPage',
            'exambroPinActive',
            'loadBalancerMirrorCount',
            'loadBalancerLinkAvailable',
            'mirrorListUrl',
            'versionSyncSettings',
            'versionSyncServers',
            'versionSyncTargets',
            'versionSyncServersText',
            'currentConfigVersion',
            'redisConfig',
            'currentCbtToken',
            'currentPinExambro'
        ));
    }

    private function writeRedisClusterConfig(array $config): void
    {
        $path = $this->redisClusterConfigFilePath();
        $dir  = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function updateVersionSyncServers(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $validated = $request->validate([
            'version_sync_servers_text' => ['nullable', 'string', 'max:20000'],

        ]);

        $rawText = (string) ($validated['version_sync_servers_text'] ?? '');
        $servers = $this->parseVersionSyncServersText($rawText);
        $this->writePersistedSetting('version_sync_servers', $servers);

          return redirect('/admin/cbt-info')
              ->with('status', 'Daftar server sinkron version.json berhasil diperbarui.'); 
    }

    public function updateVersionSyncSettings(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $validated = $request->validate([
            'version_sync_enabled' => ['nullable', 'in:0,1'],
            'version_sync_key' => ['nullable', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'version_sync_timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $enabled = (($validated['version_sync_enabled'] ?? '0') === '1');
        $syncKey = trim((string) ($validated['version_sync_key'] ?? ''));
        $timeoutSeconds = (int) ($validated['version_sync_timeout_seconds'] ?? 2);

        $this->writePersistedSetting('version_sync_enabled', $enabled ? 1 : 0);
        $this->writePersistedSetting('version_sync_key', $syncKey);
        $this->writePersistedSetting('version_sync_timeout_seconds', max(1, min($timeoutSeconds, 5)));

          return redirect('/admin/cbt-info')
              ->with('status', 'Pengaturan sinkronisasi version.json berhasil diperbarui.'); 
    }

    public function syncVersionNow(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $settings = $this->getVersionSyncSettings();
        if (! $settings['enabled']) {
            return redirect('/admin/cbt-info')
                ->withErrors(['version_sync' => 'Sinkronisasi belum diaktifkan. Aktifkan dulu pada panel Version Sync.']); 
        }

        if ($settings['key'] === '') {
            return redirect('/admin/cbt-info')
                ->withErrors(['version_sync' => 'Sync Key kosong. Isi Sync Key terlebih dahulu.']);
        }

        $payload = $this->readVersionPayloadFromFile();
        if ($payload === []) {
            return redirect('/admin/cbt-info')
                ->withErrors(['version_sync' => 'File version.json tidak valid atau tidak ditemukan.']);
        }

        $targets = $this->collectVersionSyncTargets($this->getVersionSyncServers());

        if ($targets === []) {
            return redirect('/admin/cbt-info')
                ->withErrors(['version_sync' => 'Tidak ada target mirror valid untuk sinkronisasi.']);
        }

        $successCount = 0;
        $failedHosts = [];

        foreach ($targets as $target) {
            $endpoint = (string) ($target['sync_endpoint'] ?? '');
            $host = (string) ($target['host'] ?? 'unknown-host');

            if ($endpoint === '' || ! filter_var($endpoint, FILTER_VALIDATE_URL)) {
                $failedHosts[] = $host;
                continue;
            }

            try {
                $response = Http::connectTimeout($settings['timeout_seconds'])
                    ->timeout($settings['timeout_seconds'])
                    ->withHeaders([
                        'X-Version-Sync-Key' => $settings['key'],
                        'Accept' => 'application/json',
                    ])
                    ->post($endpoint, [
                        'version' => $payload,
                        'source_host' => request()->getHost(),
                        'synced_at' => now()->toIso8601String(),
                    ]);

                if ($response->successful()) {
                    $successCount++;
                } else {
                    $failedHosts[] = $host;
                }
            } catch (\Throwable $e) {
                $failedHosts[] = $host;
            }
        }

        $targetCount = count($targets);
        if ($successCount === $targetCount) {
            return redirect('/admin/cbt-info')
                ->with('status', "Sinkronisasi version.json berhasil ke {$successCount}/{$targetCount} server JSON.");
        }

        $failedLabel = implode(', ', array_slice($failedHosts, 0, 5));

        return redirect('/admin/cbt-info')
            ->withErrors([
                'version_sync' => "Sinkronisasi parsial: berhasil {$successCount}/{$targetCount}. Gagal: {$failedLabel}",
            ]);
    }

    private function autoSyncExambroSettingsToJsonServers(): void
    {
        $settings = $this->getVersionSyncSettings();
        if (! $settings['enabled'] || $settings['key'] === '') {
            return;
        }

        $targets = $this->collectVersionSyncTargets($this->getVersionSyncServers());
        if ($targets === []) {
            return;
        }

        $payload = [
            'exambro' => $this->buildExambroSyncPayload(),
            'source_host' => request()->getHost(),
            'synced_at' => now()->toIso8601String(),
        ];

        foreach ($targets as $target) {
            $endpoint = (string) ($target['sync_endpoint'] ?? '');
            $host = (string) ($target['host'] ?? 'unknown-host');

            if ($endpoint === '' || ! filter_var($endpoint, FILTER_VALIDATE_URL)) {
                continue;
            }

            try {
                Http::connectTimeout($settings['timeout_seconds'])
                    ->timeout($settings['timeout_seconds'])
                    ->withHeaders([
                        'X-Version-Sync-Key' => $settings['key'],
                        'Accept' => 'application/json',
                    ])
                    ->post($endpoint, $payload);
            } catch (\Throwable $e) {
                Log::warning('Auto sync Exambro settings to JSON server failed.', [
                    'host' => $host,
                    'endpoint' => $endpoint,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildExambroSyncPayload(): array
    {
        return [
            'token' => $this->getExambroToken(),
            'pin_active' => $this->isExambroPinActive() ? 1 : 0,
            'warning_active' => $this->getExambroWarningValue() === 1 ? 1 : 0,
            'token_active' => $this->isExambroActive() ? 1 : 0,
            'show_pin_on_page' => $this->isExambroTokenVisibleOnPage() ? 1 : 0,
        ];
    }

    private function applyIncomingExambroSyncPayload(array $payload): void
    {
        if (array_key_exists('token', $payload)) {
            $token = strtoupper(trim((string) $payload['token']));
            if ($token !== '') {
                $this->storeExambroToken($token);
            }
        }

        if (array_key_exists('pin_active', $payload)) {
            $this->writeExambroSetting('pin_active', $this->truthy($payload['pin_active']) ? 1 : 0);
        }

        if (array_key_exists('warning_active', $payload)) {
            $this->writeExambroSetting('warning_active', $this->truthy($payload['warning_active']) ? 1 : 0);
        }

        if (array_key_exists('token_active', $payload)) {
            $this->writeExambroSetting('token_active', $this->truthy($payload['token_active']) ? 1 : 0);
        }

        if (array_key_exists('show_pin_on_page', $payload)) {
            $this->writeExambroSetting('show_pin_on_page', $this->truthy($payload['show_pin_on_page']) ? 1 : 0);
        }
    }

    public function receiveVersionSync(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders($this->corsHeaders($request));
        }

        $settings = $this->getVersionSyncSettings();
        $configuredKey = $settings['key'];
        $providedKey = trim((string) $request->header('X-Version-Sync-Key', ''));

        if ($configuredKey === '' || $providedKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid sync key.',
            ], 401)->withHeaders($this->corsHeaders($request));
        }

        $version = $request->input('version');
        $exambro = $request->input('exambro');
        $hasVersion = is_array($version);
        $hasExambro = is_array($exambro);

        if (! $hasVersion && ! $hasExambro) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid sync payload.',
            ], 422)->withHeaders($this->corsHeaders($request));
        }

        $configVersion = null;

        if ($hasVersion) {
            $configVersion = trim((string) ($version['config_version'] ?? ''));
            if ($configVersion === '') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'config_version is required.',
                ], 422)->withHeaders($this->corsHeaders($request));
            }

            $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
            $version['config_url'] = $baseUrl . '/api/config.json';
            $version['config_url_versioned'] = $baseUrl . '/api/config.json?v=' . rawurlencode($configVersion);
            $version['last_updated'] = now()->toIso8601String();
            $version['timestamp'] = now()->timestamp * 1000;

            $this->writeVersionPayloadToFile($version);
        }

        if ($hasExambro) {
            $this->applyIncomingExambroSyncPayload($exambro);
        }

        return response()->json([
            'status' => 'ok',
            'config_version' => $configVersion,
            'synced_sections' => [
                'version' => $hasVersion,
                'exambro' => $hasExambro,
            ],
            'synced_at' => now()->toIso8601String(),
        ])->withHeaders($this->corsHeaders($request));
    }

    public function updateUserAgentSettings(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $validated = $request->validate([
            'user_agent_patterns' => ['required', 'string', 'max:2000'],
        ]);

        $enabled = $request->boolean('user_agent_detection_enabled');
        $patterns = $this->normalizeUserAgentPatterns($validated['user_agent_patterns']);

        if (count($patterns) === 0) {
            return redirect('/admin/cbt-info#panel-user-agent')
                ->withErrors(['user_agent_patterns' => 'Daftar keyword User-Agent tidak boleh kosong.']);
        }

        $this->writeExambroSetting('user_agent_detection_enabled', $enabled ? 1 : 0);
        $this->writeExambroSetting('user_agent_patterns', implode("\n", $patterns));

        return redirect('/admin/cbt-info#panel-user-agent')
              ->with('status', 'Pengaturan User-Agent berhasil diperbarui.'); 
    }

    public function updateExambroEmergencyExitPin(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $validated = $request->validate([
            'exambro_exit_emergency_pin' => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $pin = trim((string) $validated['exambro_exit_emergency_pin']);
        $this->writeExambroSetting('exit_emergency_pin', $pin);

          return redirect('/admin/cbt-info#panel-exambro-settings')
              ->with('status', 'PIN darurat Exit Exambro berhasil diperbarui.'); 
    }

    public function showLogin()
    {
        if (session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin');
        }

        $info = $this->getInfoFromGarudaCbt();

        return view('cbt-info.login', [
            'schoolName' => $info->school,
            'appName' => $info->app_name,
        ]);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $adminUser = DB::table('users as u')
            ->join('users_groups as ug', 'ug.user_id', '=', 'u.id')
            ->where('ug.group_id', 1)
            ->where('u.active', 1)
            ->where('u.username', $validated['username'])
            ->select('u.id', 'u.username', 'u.password', 'u.first_name', 'u.last_name')
            ->first();

        if (! $adminUser || ! $this->passwordMatches($validated['password'], $adminUser->password)) {
            return back()->withErrors([
                'username' => 'User atau password admin tidak valid.',
            ])->onlyInput('username');
        }

        $request->session()->regenerate();
        session([
            'cbt_admin_auth' => true,
            'cbt_admin_user_id' => $adminUser->id,
            'cbt_admin_username' => $adminUser->username,
            'cbt_admin_name' => trim(($adminUser->first_name ?? '') . ' ' . ($adminUser->last_name ?? '')),
        ]);

        return redirect()->route('cbt.admin');
    }

    public function logout(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $request->session()->forget([
            'cbt_admin_auth',
            'cbt_admin_user_id',
            'cbt_admin_username',
            'cbt_admin_name',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('cbt.admin.login')->with('status', 'Anda sudah logout dari panel admin.');
    }

    public function debugCbtToken(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $row = DB::table('cbt_token')->where('id_token', 1)->first();
        return response()->json([ 'db_row' => $row ]);
    }

    public function updateServerSettings(Request $request, string $key)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $servers = $this->getConfiguredServers();
        if ($servers === []) {
            $servers = $this->legacyServerDefinitions($this->getInfoFromGarudaCbt());
        }
        $serverIndex = $this->findServerIndexByKey($servers, $key);

        if ($serverIndex < 0) {
            abort(404);
        }

        $validated = $request->validate([
            'server_name'     => ['nullable', 'string', 'max:60'],
            'server_url'      => ['required', 'url', 'max:255', 'regex:/^https?:\/\//i'],
            'server_core'     => ['nullable', 'integer', 'min:1', 'max:256'],
            'server_ram'      => ['nullable', 'string', 'max:30'],
            'server_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);


        $defaultName = 'Server ' . ($serverIndex + 1);
        $existingServer = $servers[$serverIndex] ?? [];
        $servers[$serverIndex] = [
            'key' => $key,
            'name' => trim((string) ($validated['server_name'] ?? '')) ?: $defaultName,
            'url' => $validated['server_url'],
            'core' => (int) ($validated['server_core'] ?? 4),
            'ram' => trim((string) ($validated['server_ram'] ?? '8 GB')) ?: '8 GB',
            'capacity' => (int) ($validated['server_capacity'] ?? 40),
            'hidden' => ((bool) ($existingServer['hidden'] ?? false)) === true,
            'lb_enabled' => ((bool) ($existingServer['lb_enabled'] ?? false)) === true,
            'selection_enabled' => ((bool) ($existingServer['selection_enabled'] ?? true)) === true,
            'selection_disabled_until' => $this->normalizeSelectionDisabledUntil((string) ($existingServer['selection_disabled_until'] ?? '')),
        ];

        $this->persistConfiguredServers($servers);
        $this->syncLegacyServerCachesFromConfiguredServers($servers);

        return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Pengaturan server berhasil disimpan.');
    }

    public function addServer(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $validated = $request->validate([
            'server_name' => ['nullable', 'string', 'max:60'],
            'server_url' => ['required', 'url', 'max:255', 'regex:/^https?:\/\//i'],
            'server_core' => ['nullable', 'integer', 'min:1', 'max:256'],
            'server_ram' => ['nullable', 'string', 'max:30'],
            'server_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $servers = $this->getConfiguredServers();
        if ($servers === []) {
            $servers = $this->legacyServerDefinitions($this->getInfoFromGarudaCbt());
        }
        $newIndex = count($servers) + 1;
        $newKey = $this->generateUniqueServerKey((string) ($validated['server_name'] ?? ''), $servers, $newIndex);

        $servers[] = [
            'key' => $newKey,
            'name' => trim((string) ($validated['server_name'] ?? '')) ?: ('Server ' . $newIndex),
            'url' => $validated['server_url'],
            'core' => (int) ($validated['server_core'] ?? 4),
            'ram' => trim((string) ($validated['server_ram'] ?? '8 GB')) ?: '8 GB',
            'capacity' => (int) ($validated['server_capacity'] ?? 40),
            'hidden' => false,
            'lb_enabled' => true,
            'selection_enabled' => true,
            'selection_disabled_until' => null,
        ];

        $this->persistConfiguredServers($servers);
        $this->syncLegacyServerCachesFromConfiguredServers($servers);

        return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Server baru berhasil ditambahkan.');
    }

    public function deleteServer(Request $request, string $key)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $servers = $this->getConfiguredServers();
        if ($servers === []) {
            $servers = $this->legacyServerDefinitions($this->getInfoFromGarudaCbt());
        }

        if (count($servers) <= 1) {
            return redirect(route('cbt.admin') . '#panel-web')
                ->withErrors(['server' => 'Minimal harus ada 1 server aktif.']);
        }

        $filtered = array_values(array_filter($servers, function ($server) use ($key) {
            return (string) ($server['key'] ?? '') !== $key;
        }));

        if (count($filtered) === count($servers)) {
            abort(404);
        }

        $this->persistConfiguredServers($filtered);
        $this->syncLegacyServerCachesFromConfiguredServers($filtered);

        return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Server berhasil dihapus.');
    }

    public function toggleServerVisibility(Request $request, string $key)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $servers = $this->getConfiguredServers();
        if ($servers === []) {
            $servers = $this->legacyServerDefinitions($this->getInfoFromGarudaCbt());
        }

        $serverIndex = $this->findServerIndexByKey($servers, $key);
        if ($serverIndex < 0) {
            abort(404);
        }

        $currentHidden = ((bool) ($servers[$serverIndex]['hidden'] ?? false)) === true;
        $servers[$serverIndex]['hidden'] = ! $currentHidden;

        $visibleCount = count(array_filter($servers, function ($server) {
            return ((bool) ($server['hidden'] ?? false)) !== true;
        }));

        if ($visibleCount <= 0) {
            return redirect(route('cbt.admin') . '#panel-web')
                ->withErrors(['server' => 'Minimal harus ada 1 server yang ditampilkan.']);
        }

        $this->persistConfiguredServers($servers);
        $this->syncLegacyServerCachesFromConfiguredServers($servers);

        $statusText = $servers[$serverIndex]['hidden'] ? 'disembunyikan' : 'ditampilkan';

        return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Server berhasil ' . $statusText . '.');
    }

    public function toggleServerLoadBalancing(Request $request, string $key)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $servers = $this->getConfiguredServers();
        if ($servers === []) {
            $servers = $this->legacyServerDefinitions($this->getInfoFromGarudaCbt());
        }

        $serverIndex = $this->findServerIndexByKey($servers, $key);
        if ($serverIndex < 0) {
            abort(404);
        }

        $isEnabled = ((bool) ($servers[$serverIndex]['lb_enabled'] ?? false)) === true;
        $activeCount = count(array_filter($servers, function ($server) {
            return ((bool) ($server['lb_enabled'] ?? false)) === true;
        }));

        if ($isEnabled && $activeCount <= 1) {
            return redirect(route('cbt.admin') . '#panel-web')
                ->withErrors(['server' => 'Minimal harus ada 1 server aktif untuk load balancing.']);
        }

        $servers[$serverIndex]['lb_enabled'] = ! $isEnabled;

        $this->persistConfiguredServers($servers);
        $this->syncLegacyServerCachesFromConfiguredServers($servers);

        $statusText = $servers[$serverIndex]['lb_enabled'] ? 'diaktifkan' : 'dinonaktifkan';

        return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Server berhasil ' . $statusText . ' untuk load balancing.');
    }

    public function toggleServerSelection(Request $request, string $key)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $servers = $this->getConfiguredServers();
        if ($servers === []) {
            $servers = $this->legacyServerDefinitions($this->getInfoFromGarudaCbt());
        }

        $serverIndex = $this->findServerIndexByKey($servers, $key);
        if ($serverIndex < 0) {
            abort(404);
        }

        $isEnabled = ((bool) ($servers[$serverIndex]['selection_enabled'] ?? true)) === true;
        $servers[$serverIndex]['selection_enabled'] = ! $isEnabled;

        if ($servers[$serverIndex]['selection_enabled']) {
            $servers[$serverIndex]['selection_disabled_until'] = null;
        }

        $this->persistConfiguredServers($servers);
        $this->syncLegacyServerCachesFromConfiguredServers($servers);

        $statusText = $servers[$serverIndex]['selection_enabled'] ? 'diaktifkan' : 'dinonaktifkan';

        return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Tombol pemilihan server berhasil ' . $statusText . '.');
    }

    public function setServerSelectionTimer(Request $request, string $key)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $servers = $this->getConfiguredServers();
        if ($servers === []) {
            $servers = $this->legacyServerDefinitions($this->getInfoFromGarudaCbt());
        }

        $serverIndex = $this->findServerIndexByKey($servers, $key);
        if ($serverIndex < 0) {
            abort(404);
        }

        $validated = $request->validate([
            'disable_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'clear_timer' => ['nullable', 'in:0,1'],
        ]);

        $clearTimer = ((string) ($validated['clear_timer'] ?? '0')) === '1';

        if ($clearTimer) {
            $servers[$serverIndex]['selection_enabled'] = true;
            $servers[$serverIndex]['selection_disabled_until'] = null;

            $this->persistConfiguredServers($servers);
            $this->syncLegacyServerCachesFromConfiguredServers($servers);

            return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Timer pemilihan server berhasil dihapus.');
        }

        $minutes = (int) ($validated['disable_minutes'] ?? 0);
        if ($minutes <= 0) {
            return redirect(route('cbt.admin') . '#panel-web')
                ->withErrors(['server' => 'Durasi disable server harus diisi.']);
        }

        $servers[$serverIndex]['selection_enabled'] = false;
        $servers[$serverIndex]['selection_disabled_until'] = now()->addMinutes($minutes)->toIso8601String();

        $this->persistConfiguredServers($servers);
        $this->syncLegacyServerCachesFromConfiguredServers($servers);

        return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Pemilihan server dinonaktifkan selama ' . $minutes . ' menit.');
    }

    public function setAllServerSelectionTimer(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $servers = $this->getConfiguredServers();
        if ($servers === []) {
            $servers = $this->legacyServerDefinitions($this->getInfoFromGarudaCbt());
        }

        $validated = $request->validate([
            'action' => ['required', 'in:set_timer,enable_all,disable_all'],
            'disable_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $action = (string) ($validated['action'] ?? '');

        if ($action === 'enable_all') {
            foreach ($servers as $index => $server) {
                $servers[$index]['selection_enabled'] = true;
                $servers[$index]['selection_disabled_until'] = null;
            }

            $this->persistConfiguredServers($servers);
            $this->syncLegacyServerCachesFromConfiguredServers($servers);

            return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Semua pilihan server Exambro berhasil diaktifkan.');
        }

        if ($action === 'disable_all') {
            foreach ($servers as $index => $server) {
                $servers[$index]['selection_enabled'] = false;
                $servers[$index]['selection_disabled_until'] = null;
            }

            $this->persistConfiguredServers($servers);
            $this->syncLegacyServerCachesFromConfiguredServers($servers);

            return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Semua pilihan server Exambro berhasil dinonaktifkan.');
        }

        $minutes = (int) ($validated['disable_minutes'] ?? 0);
        if ($minutes <= 0) {
            return redirect(route('cbt.admin') . '#panel-web')
                ->withErrors(['server' => 'Durasi disable untuk semua server harus diisi.']);
        }

        foreach ($servers as $index => $server) {
            $servers[$index]['selection_enabled'] = false;
            $servers[$index]['selection_disabled_until'] = now()->addMinutes($minutes)->toIso8601String();
        }

        $this->persistConfiguredServers($servers);
        $this->syncLegacyServerCachesFromConfiguredServers($servers);

        return redirect(route('cbt.admin') . '#panel-web')->with('status', 'Semua pilihan server Exambro dinonaktifkan selama ' . $minutes . ' menit.');
    }

    public function update(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:6'],
            'primary_url' => ['nullable', 'url', 'max:255', 'regex:/^https?:\/\//i'],
            'backup_url_1' => ['nullable', 'url', 'max:255', 'regex:/^https?:\/\//i'],
            'backup_url_2' => ['nullable', 'url', 'max:255', 'regex:/^https?:\/\//i'],
            'server_name_primary' => ['nullable', 'string', 'max:60'],
            'server_name_backup_1' => ['nullable', 'string', 'max:60'],
            'server_name_backup_2' => ['nullable', 'string', 'max:60'],
            'primary_core' => ['nullable', 'integer', 'min:1', 'max:256'],
            'backup1_core' => ['nullable', 'integer', 'min:1', 'max:256'],
            'backup2_core' => ['nullable', 'integer', 'min:1', 'max:256'],
            'primary_ram' => ['nullable', 'string', 'max:30'],
            'backup1_ram' => ['nullable', 'string', 'max:30'],
            'backup2_ram' => ['nullable', 'string', 'max:30'],
            'primary_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'backup1_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'backup2_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'load_balancer_url' => ['nullable', 'url', 'max:255', 'regex:/^https?:\/\//i'],
            'token_endpoint' => ['nullable', 'string', 'max:255'],
            'token_version_endpoint' => ['nullable', 'string', 'max:255'],
        ]);

        // Token update and related server URL/name writes removed per request.

        $this->writePersistedSetting('cbt_server_spec_primary_core', (int) ($validated['primary_core'] ?? 4));
        $this->writePersistedSetting('cbt_server_spec_backup1_core', (int) ($validated['backup1_core'] ?? 4));
        $this->writePersistedSetting('cbt_server_spec_backup2_core', (int) ($validated['backup2_core'] ?? 4));

        $this->writePersistedSetting('cbt_server_spec_primary_ram', trim((string) ($validated['primary_ram'] ?? '8 GB')) ?: '8 GB');
        $this->writePersistedSetting('cbt_server_spec_backup1_ram', trim((string) ($validated['backup1_ram'] ?? '8 GB')) ?: '8 GB');
        $this->writePersistedSetting('cbt_server_spec_backup2_ram', trim((string) ($validated['backup2_ram'] ?? '8 GB')) ?: '8 GB');

        $this->writePersistedSetting('cbt_server_capacity_primary', (int) ($validated['primary_capacity'] ?? 40));
        $this->writePersistedSetting('cbt_server_capacity_backup1', (int) ($validated['backup1_capacity'] ?? 40));
        $this->writePersistedSetting('cbt_server_capacity_backup2', (int) ($validated['backup2_capacity'] ?? 40));

        $this->storeServerMetaToFile([
            'server_primary_core' => (int) ($validated['primary_core'] ?? 4),
            'server_backup1_core' => (int) ($validated['backup1_core'] ?? 4),
            'server_backup2_core' => (int) ($validated['backup2_core'] ?? 4),
            'server_primary_ram' => trim((string) ($validated['primary_ram'] ?? '8 GB')) ?: '8 GB',
            'server_backup1_ram' => trim((string) ($validated['backup1_ram'] ?? '8 GB')) ?: '8 GB',
            'server_backup2_ram' => trim((string) ($validated['backup2_ram'] ?? '8 GB')) ?: '8 GB',
            'server_primary_capacity' => (int) ($validated['primary_capacity'] ?? 40),
            'server_backup1_capacity' => (int) ($validated['backup1_capacity'] ?? 40),
            'server_backup2_capacity' => (int) ($validated['backup2_capacity'] ?? 40),
        ]);

        if (! empty($validated['description'])) {
            DB::table('setting')->where('id_setting', 1)->update([
                'alamat' => $validated['description'],
            ]);
        }

        if (array_key_exists('load_balancer_url', $validated)) {
            $this->writePersistedSetting('cbt_load_balancing_url', trim((string) ($validated['load_balancer_url'] ?? '')) ?: $this->homepageUrl('/go-cbt'));
        }
        if (array_key_exists('token_endpoint', $validated)) {
            $this->writePersistedSetting('cbt_token_endpoint', trim((string) ($validated['token_endpoint'] ?? '')) ?: '/token.json');
        }
        if (array_key_exists('token_version_endpoint', $validated)) {
            $this->writePersistedSetting('cbt_token_version_endpoint', trim((string) ($validated['token_version_endpoint'] ?? '')) ?: '/version.token.json');
        }

        $this->invalidateInfoCaches();

        return redirect()->route('cbt.admin')->with('status', 'Informasi CBT berhasil diperbarui.');
    }

    public function updatePinExambro(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $validated = $request->validate([
            'pin_exambro' => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $pin = strtoupper(trim((string) $validated['pin_exambro']));
        
        // Update PIN di tabel pin_exambro
        DB::table('pin_exambro')->updateOrInsert(
            ['id' => 1],
            [
                'pin' => $pin,
                'updated' => now()->format('Y-m-d H:i:s'),
            ]
        );

        // Invalidate cache untuk PIN Exambro
        Cache::forget(self::CACHE_KEY_PIN_EXAMBRO_ROW);
        Cache::forget(self::CACHE_KEY_CBT_INFO);

        // No longer writing to public/pin.json from server; rely on DB as authoritative source.

        return redirect()->route('cbt.admin')->with('status', 'PIN Exambro berhasil diperbarui di database.');
    }

    public function fetchExambroTokenFromLb(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $validated = $request->validate([
            'api_key_source' => ['nullable', 'in:manual,db'],
            'api_key_manual' => ['nullable', 'string', 'max:255'],
        ]);

        $source = $validated['api_key_source'] ?? (string) $this->readPersistedSetting('cbt_exambro_api_key_source', 'manual');
        $apiKey = '';

        if ($source === 'db') {
            // When selecting DB as source for API key, read from cbt_token.api_key
            $apiKey = (string) (DB::table('cbt_token')->where('id_token', 1)->value('api_key') ?? '');
        } else {
            $apiKey = trim((string) ($validated['api_key_manual'] ?? ''));
            // persist manual key so admin doesn't need to retype
            if ($apiKey !== '') {
                $this->writePersistedSetting('cbt_exambro_api_key', $apiKey);
                $this->writePersistedSetting('cbt_exambro_api_key_source', 'manual');
            }
        }

        // If no API key available, error
        if ($apiKey === '') {
            return redirect()->route('cbt.admin')->withErrors(['api_key' => 'API key tidak ditemukan. Isi manual atau pilih sumber DB.']);
        }

        // Get LB base URL
        $lb = (string) $this->readPersistedSetting('cbt_load_balancing_url', $this->homepageUrl('/go-cbt'));
        $lb = rtrim($lb, '/');

        $tokenEndpoint = (string) $this->readPersistedSetting('cbt_token_endpoint', '/token.json');
        $tokenVersionEndpoint = (string) $this->readPersistedSetting('cbt_token_version_endpoint', '/version.token.json');

        $buildUrl = function (string $base, string $path) use ($apiKey) {
            $path = trim($path);
            if ($path === '') {
                return '';
            }

            // If path already looks like a full URL, use it directly (supports {api_key} placeholder)
            if (preg_match('#^https?://#i', $path)) {
                if (str_contains($path, '{api_key}')) {
                    return str_replace('{api_key}', rawurlencode($apiKey), $path);
                }

                $sep = str_contains($path, '?') ? '&' : '?';
                return $path . $sep . 'api_key=' . rawurlencode($apiKey);
            }

            // If path contains placeholder {api_key}, build base+path then replace
            $base = rtrim($base, '/');
            $path = '/' . ltrim($path, '/');
            $url = $base . $path;

            if (str_contains($path, '{api_key}')) {
                return str_replace('{api_key}', rawurlencode($apiKey), $url);
            }

            $sep = str_contains($url, '?') ? '&' : '?';
            return $url . $sep . 'api_key=' . rawurlencode($apiKey);
        };

        $candidates = [];
        if ($tokenEndpoint !== '') {
            $candidates[] = $buildUrl($lb, $tokenEndpoint);
        }
        if ($tokenVersionEndpoint !== '') {
            $candidates[] = $buildUrl($lb, $tokenVersionEndpoint);
        }

        $found = false;
        $fetchedToken = '';
        $fetchedPin = null;

        foreach ($candidates as $url) {
            try {
                $res = Http::connectTimeout(3)->timeout(5)->acceptJson()->get($url);
            } catch (\Throwable $e) {
                continue;
            }

            if (! $res->successful()) {
                continue;
            }

            $json = null;
            try {
                $json = $res->json();
            } catch (\Throwable $e) {
                $json = null;
            }

            if (! is_array($json)) {
                continue;
            }

            // Accept fields: token, cbt_token, pin, exambro_token
            if (! empty($json['token'] ?? $json['cbt_token'] ?? $json['exambro_token'] ?? null)) {
                $fetchedToken = strtoupper(trim((string) ($json['token'] ?? $json['cbt_token'] ?? $json['exambro_token'])));
            }

            if (! empty($json['pin'] ?? null)) {
                $fetchedPin = strtoupper(trim((string) $json['pin']));
            }

            if ($fetchedToken !== '' || $fetchedPin !== null) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            return redirect()->route('cbt.admin')->withErrors(['fetch' => 'Gagal mengambil token dari LB endpoint. Pastikan URL LB dan API key benar.']);
        }

        // Persist token to DB if found
        if ($fetchedToken !== '') {
            DB::table('cbt_token')->updateOrInsert(
                ['id_token' => 1],
                [
                    'token' => $fetchedToken,
                    'jarak' => DB::raw('jarak'),
                    'updated' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now(),
                ]
            );
            Cache::forget(self::CACHE_KEY_CBT_TOKEN_ROW);
        }

        // Persist PIN if provided
        if ($fetchedPin !== null) {
            DB::table('pin_exambro')->updateOrInsert(
                ['id' => 1],
                [
                    'pin' => $fetchedPin,
                    'updated' => now()->format('Y-m-d H:i:s'),
                ]
            );
            Cache::forget(self::CACHE_KEY_PIN_EXAMBRO_ROW);

            // no longer writing public/pin.json here; DB is authoritative
        }

        $this->invalidateInfoCaches();

        $msg = 'Sinkronisasi token';
        if ($fetchedToken !== '') {
            $msg .= " berhasil (token: {$fetchedToken})";
        }
        if ($fetchedPin !== null) {
            $msg .= " dan PIN diperbarui";
        }

        return redirect()->route('cbt.admin')->with('status', $msg . '.');
    }

    public function updateExambroTokenFromAdmin(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $validated = $request->validate([
            'token_manual' => ['required', 'string', 'max:32'],
        ]);

        $token = strtoupper(trim((string) ($validated['token_manual'] ?? '')));

        DB::table('cbt_token')->updateOrInsert(
            ['id_token' => 1],
            [
                'token' => $token,
                'updated' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now(),
            ]
        );

        Cache::forget(self::CACHE_KEY_CBT_TOKEN_ROW);
        Cache::forget(self::CACHE_KEY_CBT_INFO);

        return redirect()->route('cbt.admin')->with('status', 'Token CBT berhasil diperbarui.');
    }

    // flushCache removed — feature deprecated/disabled

    public function toggleExambro(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentStatus = $this->isExambroActive();
        $this->writeExambroSetting('token_active', ! $currentStatus);
        $this->syncConfigJsonWithAdminSettings();

        $statusLabel = ! $currentStatus ? 'AKTIF' : 'NON-AKTIF';

        return redirect()->route('cbt.admin')->with('status', "Status token Exambro diubah menjadi {$statusLabel}.");
    }

    public function generateExambroToken(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $cbtToken = strtoupper((string) $this->getInfoFromGarudaCbt()->cbt_token);
        $newToken = '';

        for ($i = 0; $i < 10; $i++) {
            $candidate = (string) random_int(100000, 999999);
            if ($candidate !== $cbtToken) {
                $newToken = $candidate;
                break;
            }
        }

        if ($newToken === '') {
            $newToken = strtoupper(Str::substr(Str::random(8), 0, 6));
        }

        $this->storeExambroToken($newToken);
        $this->autoSyncExambroSettingsToJsonServers();
        $this->syncConfigJsonWithAdminSettings();

        return redirect()->route('cbt.admin')->with('status', 'PIN Exambro berhasil digenerate dan dipisahkan dari token soal.');
    }

    public function toggleExambroWarning(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentValue = $this->getExambroWarningValue();
        $nextValue = $currentValue === 1 ? 0 : 1;

        $this->writeExambroSetting('warning_active', $nextValue);
        $this->autoSyncExambroSettingsToJsonServers();
        $this->syncConfigJsonWithAdminSettings();

        return redirect()->route('cbt.admin')->with('status', 'Pengaturan peringatan Exambro diubah menjadi ' . ($nextValue === 1 ? 'ON (1)' : 'OFF (0)') . '.');
    }

    public function toggleExambroTokenVisibilityForPage(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentValue = $this->isExambroTokenVisibleOnPage();
        $nextValue = $currentValue ? 0 : 1;

        $this->writeExambroSetting('show_pin_on_page', $nextValue);

        return redirect()->route('cbt.admin')->with('status', 'Tampilan PIN Exambro di halaman Exambro diubah menjadi ' . ($nextValue === 1 ? 'TAMPIL' : 'SEMBUNYI') . '.');
    }

    public function toggleExambroPinStatus(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentStatus = $this->isExambroPinActive();
        $this->writeExambroSetting('pin_active', ! $currentStatus);
        $this->autoSyncExambroSettingsToJsonServers();
        $this->syncConfigJsonWithAdminSettings();

        $statusLabel = ! $currentStatus ? 'AKTIF' : 'NON-AKTIF';

        return redirect()->route('cbt.admin')->with('status', "Status PIN Exambro diubah menjadi {$statusLabel}.");
    }

    public function downloadNeoExamZip(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        if (! class_exists(ZipArchive::class)) {
            return redirect('/admin/cbt-info#panel-api')
                ->withErrors(['zip' => 'Ekstensi ZIP di PHP belum aktif di server.']);
        }

        $sourceDir = public_path('download/Neo_Exam');
        if (! File::isDirectory($sourceDir)) {
            return redirect('/admin/cbt-info#panel-api')
                ->withErrors(['zip' => 'Folder Neo_Exam tidak ditemukan di server.']);
        }

        $targetDir = storage_path('app/private/downloads');
        if (! File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $fileName = 'neo_exam_' . now()->format('Ymd_His') . '.zip';
        $zipPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect('/admin/cbt-info#panel-api')
                ->withErrors(['zip' => 'Gagal membuat file ZIP Neo_Exam.']);
        }

        $rootInZip = 'Neo_Exam';
        $zip->addEmptyDir($rootInZip);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $sourceBaseLength = strlen($sourceDir) + 1;
        foreach ($files as $item) {
            $fullPath = $item->getPathname();
            $relativePath = substr($fullPath, $sourceBaseLength);
            if ($relativePath === false || $relativePath === '') {
                continue;
            }

            $zipEntryPath = $rootInZip . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            if ($item->isDir()) {
                $zip->addEmptyDir($zipEntryPath);
                continue;
            }

            $zip->addFile($fullPath, $zipEntryPath);
        }

        $zip->close();

        return response()->download($zipPath, $fileName, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ])->deleteFileAfterSend(true);
    }

    private function isExambroActive(): bool
    {
        $raw = $this->readExambroSetting('token_active', false);

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }

        if (is_string($raw)) {
            $normalized = strtolower(trim($raw));

            return in_array($normalized, ['1', 'true', 'yes', 'on', 'active', 'aktif'], true);
        }

        return (bool) $raw;
    }

    private function isExambroPinActive(): bool
    {
        $raw = $this->readExambroSetting('pin_active', true);

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }

        if (is_string($raw)) {
            $normalized = strtolower(trim($raw));

            return in_array($normalized, ['1', 'true', 'yes', 'on', 'active', 'aktif'], true);
        }

        return (bool) $raw;
    }

    private function getExambroEmergencyExitPin(): string
    {
        $persisted = trim((string) $this->readExambroSetting('exit_emergency_pin', ''));
        if ($persisted !== '') {
            return $persisted;
        }

        $envPin = trim((string) env('EXAMBRO_EXIT_EMERGENCY_PIN', '864209'));
        if ($envPin !== '') {
            return $envPin;
        }

        return '864209';
    }

    private function hasPersistedExambroEmergencyExitPin(): bool
    {
        return trim((string) $this->readExambroSetting('exit_emergency_pin', '')) !== '';
    }

    private function getExambroToken(): string
    {
        // Always read Exambro token (PIN) from database table `pin_exambro`.
        $pinRow = $this->getPinExambroRow();
        if ($pinRow && ! empty($pinRow->pin)) {
            return strtoupper(trim((string) $pinRow->pin));
        }

        // If not present, return empty string — do not fall back to files or env.
        return '';
    }

    private function getExambroTokenSource(): string
    {
        $pinRow = $this->getPinExambroRow();
        if ($pinRow && ! empty($pinRow->pin)) {
            return 'database';
        }

        return 'unset';
    }

    private function exambroTokenFilePath(): string
    {
        return storage_path('app/private/exambro-token.json');
    }

    private function readExambroTokenFromFile(): string
    {
        $path = $this->exambroTokenFilePath();

        if (! File::exists($path)) {
            return '';
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return '';
        }

        $token = trim((string) ($decoded['token'] ?? ''));

        return strtoupper($token);
    }

    private function storeExambroToken(string $token): void
    {
        $this->writeExambroSetting('token', strtoupper(trim($token)));

        $path = $this->exambroTokenFilePath();
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($path, json_encode([
            'token' => strtoupper(trim($token)),
            'generated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function exambroSettingsFilePath(): string
    {
        return storage_path('app/private/exambro-settings.json');
    }

    private function readAllExambroSettings(): array
    {
        $path = $this->exambroSettingsFilePath();

        if (! File::exists($path)) {
            return [];
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeAllExambroSettings(array $settings): void
    {
        $path = $this->exambroSettingsFilePath();
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $settings['updated_at'] = now()->toIso8601String();
        File::put($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->cacheForgetSafely(self::CACHE_KEY_CBT_INFO);
    }

    private function readExambroSetting(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'exambro_setting:' . $key;
        $cached = Cache::get($cacheKey, '__missing__');

        if ($cached !== '__missing__') {
            return $cached;
        }

        $settings = $this->readAllExambroSettings();

        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        $value = $settings[$key];
        $this->cacheForeverSafely($cacheKey, $value);

        return $value;
    }

    private function writeExambroSetting(string $key, mixed $value): void
    {
        $cacheKey = 'exambro_setting:' . $key;
        $this->cacheForeverSafely($cacheKey, $value);
        $this->cacheForgetSafely(self::CACHE_KEY_CBT_INFO);

        $settings = $this->readAllExambroSettings();
        $settings[$key] = $value;
        $this->writeAllExambroSettings($settings);
    }

    private function getExambroWarningValue(): int
    {
        $raw = $this->readExambroSetting('warning_active', 1);

        if (is_int($raw) || is_float($raw)) {
            return ((int) $raw) === 1 ? 1 : 0;
        }

        if (is_bool($raw)) {
            return $raw ? 1 : 0;
        }

        if (is_string($raw)) {
            $normalized = strtolower(trim($raw));

            return in_array($normalized, ['1', 'true', 'yes', 'on', 'active', 'aktif'], true) ? 1 : 0;
        }

        return 0;
    }

    private function isExambroTokenVisibleOnPage(): bool
    {
        $raw = $this->readExambroSetting('show_pin_on_page', 1);

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }

        if (is_string($raw)) {
            $normalized = strtolower(trim($raw));

            return in_array($normalized, ['1', 'true', 'yes', 'on', 'active', 'aktif'], true);
        }

        return false;
    }

    private function getInfoFromGarudaCbt(): object
    {
        $payload = Cache::remember(
            self::CACHE_KEY_CBT_INFO,
            now()->addSeconds($this->dbRefreshIntervalSeconds()),
            function () {
                $tokenData = $this->getCbtTokenRow();
                $settingData = $this->getSettingRow();
                $persistentServerMeta = $this->readServerMetaFromFile();

                $tokenUpdatedAt = $tokenData?->updated;
                $tokenLifetimeMinutes = isset($tokenData?->jarak) ? (int) $tokenData->jarak : 0;

                $tokenValidUntil = null;
                if (! empty($tokenUpdatedAt) && $tokenLifetimeMinutes > 0) {
                    $tokenValidUntil = now()->parse($tokenUpdatedAt)->addMinutes($tokenLifetimeMinutes)->format('d-m-Y H:i:s');
                }

                return [
                    'token' => $tokenData?->token ?? 'BELUM-DISET',
                    'cbt_token' => $tokenData?->token ?? 'BELUM-DISET',
                    'exambro_token' => $this->getExambroToken(),
                    'cbt_url' => $settingData?->web ?? config('app.url'),
                    'cbt_backup_url_1' => (string) $this->readPersistedSetting('cbt_backup_url_1', $settingData?->web ?? config('app.url')),
                    'cbt_backup_url_2' => (string) $this->readPersistedSetting('cbt_backup_url_2', $settingData?->web ?? config('app.url')),
                    'server_name_primary' => (string) $this->readPersistedSetting('cbt_server_name_primary', 'Server Utama'),
                    'server_name_backup_1' => (string) $this->readPersistedSetting('cbt_server_name_backup_1', 'Server 1'),
                    'server_name_backup_2' => (string) $this->readPersistedSetting('cbt_server_name_backup_2', 'Server 2'),
                    'server_primary_core' => max(1, (int) $this->readPersistedSetting('cbt_server_spec_primary_core', (int) ($persistentServerMeta['server_primary_core'] ?? 4))),
                    'server_backup1_core' => max(1, (int) $this->readPersistedSetting('cbt_server_spec_backup1_core', (int) ($persistentServerMeta['server_backup1_core'] ?? 4))),
                    'server_backup2_core' => max(1, (int) $this->readPersistedSetting('cbt_server_spec_backup2_core', (int) ($persistentServerMeta['server_backup2_core'] ?? 4))),
                    'server_primary_ram' => (string) $this->readPersistedSetting('cbt_server_spec_primary_ram', (string) ($persistentServerMeta['server_primary_ram'] ?? '8 GB')),
                    'server_backup1_ram' => (string) $this->readPersistedSetting('cbt_server_spec_backup1_ram', (string) ($persistentServerMeta['server_backup1_ram'] ?? '8 GB')),
                    'server_backup2_ram' => (string) $this->readPersistedSetting('cbt_server_spec_backup2_ram', (string) ($persistentServerMeta['server_backup2_ram'] ?? '8 GB')),
                    'server_primary_capacity' => max(1, (int) $this->readPersistedSetting('cbt_server_capacity_primary', (int) ($persistentServerMeta['server_primary_capacity'] ?? 40))),
                    'server_backup1_capacity' => max(1, (int) $this->readPersistedSetting('cbt_server_capacity_backup1', (int) ($persistentServerMeta['server_backup1_capacity'] ?? 40))),
                    'server_backup2_capacity' => max(1, (int) $this->readPersistedSetting('cbt_server_capacity_backup2', (int) ($persistentServerMeta['server_backup2_capacity'] ?? 40))),
                    'description' => $settingData?->alamat ?? 'Silakan perbarui token dan URL CBT melalui halaman admin.',
                    'school' => $settingData?->sekolah ?? 'GARUDA CBT',
                    'load_balancing_url' => (string) $this->readPersistedSetting('cbt_load_balancing_url', $this->homepageUrl('/go-cbt')),
                    'app_name' => $settingData?->nama_aplikasi ?? 'GARUDA CBT',
                    'token_updated_at' => $tokenUpdatedAt ? now()->parse($tokenUpdatedAt)->format('d-m-Y H:i:s') : null,
                    'token_valid_until' => $tokenValidUntil,
                ];
            }
        );

        return (object) $payload;
    }

    private function getCbtTokenRow(): ?object
    {
        return Cache::remember(
            self::CACHE_KEY_CBT_TOKEN_ROW,
            now()->addSeconds($this->dbRefreshIntervalSeconds()),
            function () {
                return DB::table('cbt_token')->where('id_token', 1)->first();
            }
        );
    }

    /**
     * Read CBT token directly from database (bypass controller-level cache).
     */
    private function readCbtTokenDirect(): string
    {
        $row = DB::table('cbt_token')->where('id_token', 1)->first();

        return strtoupper((string) ($row->token ?? 'BELUM-DISET'));
    }

    // writePinJsonFromDb removed — writing to public/pin.json is deprecated.

    private function getSettingRow(): ?object
    {
        return Cache::remember(
            self::CACHE_KEY_SETTING_ROW,
            now()->addSeconds($this->dbRefreshIntervalSeconds()),
            function () {
                return DB::table('setting')->where('id_setting', 1)->first();
            }
        );
    }

    private function getPinExambroRow(): ?object
    {
        return Cache::remember(
            self::CACHE_KEY_PIN_EXAMBRO_ROW,
            now()->addSeconds($this->dbRefreshIntervalSeconds()),
            function () {
                return DB::table('pin_exambro')->where('id', 1)->first();
            }
        );
    }

    private function dbRefreshIntervalSeconds(): int
    {
        $seconds = (int) env('CBT_DB_REFRESH_INTERVAL_SECONDS', self::DB_REFRESH_INTERVAL_SECONDS);

        return max(30, min($seconds, 900));
    }

    private function invalidateInfoCaches(): void
    {
        Cache::forget(self::CACHE_KEY_CBT_INFO);
        Cache::forget(self::CACHE_KEY_CBT_TOKEN_ROW);
        Cache::forget(self::CACHE_KEY_SETTING_ROW);
    }

    private function buildServerList(object $info, bool $includeHidden = false): array
    {
        $servers = $this->getConfiguredServers();

        if ($servers === []) {
            $servers = $this->legacyServerDefinitions($info);
        }

        foreach ($servers as &$server) {
            $isUp = $this->isServerUp($server['url']);
            $serverKey = (string) ($server['key'] ?? '');
            $isHidden = ((bool) ($server['hidden'] ?? false)) === true;
            $lbEnabled = ((bool) ($server['lb_enabled'] ?? false)) === true;
            $hasUrl = ! empty($server['url']) && filter_var((string) $server['url'], FILTER_VALIDATE_URL) !== false;
            $runtimeLbState = $this->resolveRuntimeLoadBalancerState($serverKey, $isUp, $lbEnabled, ! $isHidden, $hasUrl);
            $loginCount = $this->getServerLoginCount((string) ($server['key'] ?? ''));
            $activeUserCount = $this->getServerActiveUserCount((string) ($server['key'] ?? ''));
            $indicatorMeta = $this->getLoginIndicatorMeta($activeUserCount, (int) ($server['capacity'] ?? 40));

            $server['is_up'] = $isUp;
            $server['status_label'] = $isUp ? 'SERVER UP' : 'SERVER DOWN';
            $server['status_class'] = $isUp ? 'up' : 'down';
            $server['hidden'] = $isHidden;
            $server['lb_runtime_enabled'] = $runtimeLbState['runtime_enabled'];
            $server['auto_lb_excluded'] = $runtimeLbState['excluded'];
            $server['auto_lb_down_streak'] = $runtimeLbState['down_streak'];
            $server['auto_lb_disabled_until'] = $runtimeLbState['disabled_until'];
            $server['country_code'] = $this->extractCountryCodeFromUrl($server['url']);
            $server['login_count'] = $loginCount;
            $server['active_user_count'] = $activeUserCount;
            $server['login_indicator'] = $indicatorMeta['key'];
            $server['login_indicator_label'] = $indicatorMeta['label'];

            $selectionRuntime = $this->resolveServerSelectionRuntime($server);
            $server['selection_enabled'] = ((bool) ($server['selection_enabled'] ?? true)) === true;
            $server['selection_disabled_until'] = $selectionRuntime['disabled_until'];
            $server['selection_runtime_enabled'] = $selectionRuntime['runtime_enabled'];
            $server['selection_timed_disabled'] = $selectionRuntime['timed_disabled'];
        }
        unset($server);

        if (! $includeHidden) {
            $servers = array_values(array_filter($servers, function ($server) {
                return ((bool) ($server['hidden'] ?? false)) !== true;
            }));
        }

        return $servers;
    }

    private function attachCachedQrCodes(array $servers): array
    {
        $exambroPageUrl = route('cbt.exambro.page');

        foreach ($servers as &$server) {
            $server['qr_svg'] = null;

            if (! filter_var($exambroPageUrl, FILTER_VALIDATE_URL)) {
                continue;
            }

            $cacheKey = 'server_qr_svg:exambro_page:v3:' . sha1($exambroPageUrl);
            $server['qr_svg'] = Cache::remember($cacheKey, now()->addDay(), function () use ($exambroPageUrl) {
                return $this->generateLocalQrSvg($exambroPageUrl, 220);
            });
        }
        unset($server);

        return $servers;
    }

    private function generateLocalQrSvg(string $value, int $size = 220): ?string
    {
        try {
            $renderer = new ImageRenderer(
                new RendererStyle(max(120, $size)),
                new SvgImageBackEnd()
            );

            $writer = new Writer($renderer);

            return $writer->writeString($value);
        } catch (\Throwable $e) {
            if (! function_exists('shell_exec')) {
                return null;
            }

            $binary = trim((string) @\shell_exec('command -v qrencode 2>/dev/null'));
            if ($binary === '') {
                return null;
            }

            $sizeOption = max(4, min(16, (int) round($size / 30)));
            $command = $binary
                . ' -t SVG'
                . ' -s ' . $sizeOption
                . ' -m 1'
                . ' -o - -- '
                . escapeshellarg($value);

            $svg = @\shell_exec($command);
            if (! is_string($svg) || trim($svg) === '' || ! str_contains($svg, '<svg')) {
                return null;
            }

            return $svg;
        }
    }

    private function legacyServerDefinitions(object $info): array
    {
        return [
            [
                'key' => 'primary',
                'name' => $info->server_name_primary,
                'url' => $info->cbt_url,
                'core' => max(1, (int) ($info->server_primary_core ?? 4)),
                'ram' => (string) ($info->server_primary_ram ?? '8 GB'),
                'capacity' => max(1, (int) ($info->server_primary_capacity ?? 40)),
                'hidden' => false,
                'lb_enabled' => true,
                'selection_enabled' => true,
                'selection_disabled_until' => null,
            ],
            [
                'key' => 'backup1',
                'name' => $info->server_name_backup_1,
                'url' => $info->cbt_backup_url_1,
                'core' => max(1, (int) ($info->server_backup1_core ?? 4)),
                'ram' => (string) ($info->server_backup1_ram ?? '8 GB'),
                'capacity' => max(1, (int) ($info->server_backup1_capacity ?? 40)),
                'hidden' => false,
                'lb_enabled' => true,
                'selection_enabled' => true,
                'selection_disabled_until' => null,
            ],
            [
                'key' => 'backup2',
                'name' => $info->server_name_backup_2,
                'url' => $info->cbt_backup_url_2,
                'core' => max(1, (int) ($info->server_backup2_core ?? 4)),
                'ram' => (string) ($info->server_backup2_ram ?? '8 GB'),
                'capacity' => max(1, (int) ($info->server_backup2_capacity ?? 40)),
                'hidden' => false,
                'lb_enabled' => true,
                'selection_enabled' => true,
                'selection_disabled_until' => null,
            ],
        ];
    }

    private function selectAvailableServer(array $servers): array
    {
        $serversWithUrl = array_values(array_filter($servers, function ($server) {
            return ! empty($server['url']);
        }));

        $lbPool = array_values(array_filter($serversWithUrl, function ($server) {
            return ((bool) ($server['lb_runtime_enabled'] ?? ($server['lb_enabled'] ?? false))) === true;
        }));

        if (count($lbPool) > 1) {
            $candidatePool = $lbPool;
        } elseif (count($serversWithUrl) > 1) {
            $candidatePool = $serversWithUrl;
        } elseif ($serversWithUrl !== []) {
            $candidatePool = $serversWithUrl;
        } else {
            $candidatePool = $servers;
        }

        $upServers = array_values(array_filter($candidatePool, function ($server) {
            return (($server['is_up'] ?? false) === true) && ! empty($server['url']);
        }));

        if ($upServers !== []) {
            usort($upServers, function ($left, $right) {
                $leftRatio = $this->serverLoadRatio((array) $left);
                $rightRatio = $this->serverLoadRatio((array) $right);

                return $leftRatio <=> $rightRatio;
            });

            // Prefer server yang tidak high load dengan rasio paling kecil.
            foreach ($upServers as $server) {
                if (! $this->isServerHighLoad((array) $server)) {
                    return $server;
                }
            }

            // Jika semua high load, tetap pilih yang paling ringan (least bad), bukan first-up acak.
            return $upServers[0];
        }

        if ($serversWithUrl !== []) {
            usort($serversWithUrl, function ($left, $right) {
                $leftRatio = $this->serverLoadRatio((array) $left);
                $rightRatio = $this->serverLoadRatio((array) $right);

                return $leftRatio <=> $rightRatio;
            });

            return $serversWithUrl[0];
        }

        return [
            'key' => null,
            'name' => null,
            'url' => null,
            'is_up' => false,
            'status_label' => 'SERVER DOWN',
        ];
    }

    private function isServerUp(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $candidateUrls = $this->healthCheckCandidates($url);
        $primaryUrl = $candidateUrls[0] ?? $url;

        $urlHash = sha1($primaryUrl);
        $cacheKey = 'server_up_status:' . $urlHash;
        $knownStatusKey = 'server_up_status_known:' . $urlHash;
        $lockKey = 'server_up_status_lock:' . $urlHash;
        $cached = Cache::get($cacheKey, null);
        if (is_bool($cached)) {
            return $cached;
        }

        $knownStatus = Cache::get($knownStatusKey, null);
        if (! Cache::add($lockKey, 1, now()->addSeconds(self::SERVER_STATUS_LOCK_SECONDS))) {
            return is_bool($knownStatus) ? $knownStatus : false;
        }

        $isUp = false;

        try {
            $connectTimeout = max(1, (int) env('SERVER_HEALTH_CONNECT_TIMEOUT_SECONDS', 1));
            $requestTimeout = max(1, (int) env('SERVER_HEALTH_TIMEOUT_SECONDS', 2));

            $httpClient = Http::connectTimeout($connectTimeout)
                ->timeout($requestTimeout)
                ->withOptions(['allow_redirects' => true, 'http_errors' => false]);

            foreach ($candidateUrls as $candidateUrl) {
                $statusCode = $this->fetchHealthStatusCode($httpClient, $candidateUrl);

                if ($statusCode >= 200 && $statusCode < 400) {
                    $isUp = true;
                    break;
                }

                if (in_array($statusCode, [401, 403], true)) {
                    $isUp = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            $isUp = false;
        } finally {
            Cache::forget($lockKey);
        }

        Cache::put(
            $cacheKey,
            $isUp,
            now()->addSeconds($isUp ? $this->serverStatusUpTtlSeconds() : $this->serverStatusDownTtlSeconds())
        );
        Cache::put($knownStatusKey, $isUp, now()->addSeconds(self::SERVER_STATUS_KNOWN_TTL_SECONDS));

        return $isUp;
    }

    private function serverStatusUpTtlSeconds(): int
    {
        $seconds = (int) env('SERVER_STATUS_UP_TTL_SECONDS', self::SERVER_STATUS_UP_TTL_SECONDS);

        return max(5, min($seconds, 300));
    }

    private function serverStatusDownTtlSeconds(): int
    {
        $seconds = (int) env('SERVER_STATUS_DOWN_TTL_SECONDS', self::SERVER_STATUS_DOWN_TTL_SECONDS);

        return max(3, min($seconds, 120));
    }

    private function autoLbDownStreakThreshold(): int
    {
        $value = (int) env('SERVER_AUTO_LB_DOWN_STREAK', 3);

        return max(2, min($value, 10));
    }

    private function autoLbRecoveryUpStreakThreshold(): int
    {
        $value = (int) env('SERVER_AUTO_LB_RECOVERY_UP_STREAK', 2);

        return max(1, min($value, 10));
    }

    private function autoLbCooldownSeconds(): int
    {
        $value = (int) env('SERVER_AUTO_LB_COOLDOWN_SECONDS', 90);

        return max(15, min($value, 1800));
    }

    private function autoLbStateCacheKey(string $serverKey): string
    {
        return 'server_auto_lb_state:' . $serverKey;
    }

    private function resolveRuntimeLoadBalancerState(
        string $serverKey,
        bool $isUp,
        bool $lbEnabled,
        bool $isVisible,
        bool $hasUrl
    ): array {
        if ($serverKey === '' || ! $lbEnabled || ! $isVisible || ! $hasUrl) {
            if ($serverKey !== '') {
                Cache::forget($this->autoLbStateCacheKey($serverKey));
            }

            return [
                'runtime_enabled' => false,
                'excluded' => false,
                'down_streak' => 0,
                'disabled_until' => null,
            ];
        }

        $cacheKey = $this->autoLbStateCacheKey($serverKey);
        $state = Cache::get($cacheKey, []);
        if (! is_array($state)) {
            $state = [];
        }

        $downStreak = max(0, (int) ($state['down_streak'] ?? 0));
        $upStreak = max(0, (int) ($state['up_streak'] ?? 0));
        $disabledUntilTs = max(0, (int) ($state['disabled_until_ts'] ?? 0));
        $nowTs = now()->timestamp;

        if ($isUp) {
            $upStreak++;
            $downStreak = 0;

            if ($disabledUntilTs > $nowTs && $upStreak >= $this->autoLbRecoveryUpStreakThreshold()) {
                $disabledUntilTs = 0;
            }
        } else {
            $downStreak++;
            $upStreak = 0;

            if ($downStreak >= $this->autoLbDownStreakThreshold()) {
                $disabledUntilTs = max($disabledUntilTs, $nowTs + $this->autoLbCooldownSeconds());
            }
        }

        $excluded = $disabledUntilTs > $nowTs;

        Cache::put(
            $cacheKey,
            [
                'down_streak' => $downStreak,
                'up_streak' => $upStreak,
                'disabled_until_ts' => $disabledUntilTs,
            ],
            now()->addSeconds(max(300, ($this->autoLbCooldownSeconds() * 2)))
        );

        return [
            'runtime_enabled' => $lbEnabled && ! $excluded,
            'excluded' => $excluded,
            'down_streak' => $downStreak,
            'disabled_until' => $disabledUntilTs > 0 ? date('c', $disabledUntilTs) : null,
        ];
    }

    private function healthCheckCandidates(string $url): array
    {
        $url = $this->normalizeLegacyHealthCheckBaseUrl($url);
        $candidates = [];

        foreach ($this->serverHealthCheckPaths() as $path) {
            $candidate = $this->buildHealthCheckUrl($url, $path);
            if ($candidate !== '') {
                $candidates[$candidate] = $candidate;
            }
        }

        $candidates[$url] = $url;

        return array_values($candidates);
    }

    private function serverHealthCheckPaths(): array
    {
        $raw = trim((string) env('SERVER_HEALTH_CHECK_PATHS', '/up,/api/config/health'));
        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $paths = [];

        foreach ($parts as $part) {
            $path = '/' . ltrim(trim((string) $part), '/');
            if ($path !== '/') {
                $paths[$path] = $path;
            }
        }

        if ($paths === []) {
            $paths['/up'] = '/up';
            $paths['/api/config/health'] = '/api/config/health';
        }

        return array_values($paths);
    }

    private function buildHealthCheckUrl(string $baseUrl, string $path): string
    {
        $parts = parse_url($baseUrl);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = isset($parts['scheme']) ? (string) $parts['scheme'] : 'https';
        $host = isset($parts['host']) ? (string) $parts['host'] : '';
        if ($host === '') {
            return '';
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return $scheme . '://' . $host . $port . '/' . ltrim($path, '/');
    }

    private function fetchHealthStatusCode($httpClient, string $url): int
    {
        $response = $httpClient->head($url);
        $statusCode = (int) $response->status();

        if (in_array($statusCode, [405, 501], true)) {
            $response = $httpClient->get($url);
            $statusCode = (int) $response->status();
        }

        return $statusCode;
    }

    private function normalizeLegacyHealthCheckBaseUrl(string $url): string
    {
        $targetHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($targetHost === '') {
            return $url;
        }

        $legacyHosts = $this->legacyPublicHosts();
        if ($legacyHosts === [] || ! in_array($targetHost, $legacyHosts, true)) {
            return $url;
        }

        $currentBaseUrl = $this->resolveCurrentBaseUrl();
        if ($currentBaseUrl === '' || ! filter_var($currentBaseUrl, FILTER_VALIDATE_URL)) {
            return $url;
        }

        $appScheme = strtolower((string) parse_url($currentBaseUrl, PHP_URL_SCHEME));
        $appHost = strtolower((string) parse_url($currentBaseUrl, PHP_URL_HOST));
        $appPort = parse_url($currentBaseUrl, PHP_URL_PORT);
        $appPortSuffix = is_int($appPort) ? ':' . $appPort : '';

        if ($appHost === '' || strcasecmp($appHost, $targetHost) === 0) {
            return $url;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $query = isset($parts['query']) ? '?' . (string) $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . (string) $parts['fragment'] : '';

        return ($appScheme !== '' ? $appScheme : 'https') . '://' . $appHost . $appPortSuffix . $path . $query . $fragment;
    }

    private function legacyPublicHosts(): array
    {
        $raw = trim((string) env('LEGACY_PUBLIC_HOSTS', ''));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $hosts = [];

        foreach ($parts as $part) {
            $host = strtolower(trim((string) $part));
            if ($host !== '') {
                $hosts[$host] = $host;
            }
        }

        return array_values($hosts);
    }

    private function resolveCurrentBaseUrl(): string
    {
        $request = request();
        if ($request instanceof Request) {
            $host = trim((string) $request->getHost());
            if ($host !== '') {
                return $request->getSchemeAndHttpHost();
            }
        }

        $appUrl = trim((string) config('app.url', ''));

        return $appUrl;
    }

    private function serverLoginCacheKey(string $serverKey): string
    {
        return self::SERVER_LOGIN_COUNTER_PREFIX . $serverKey;
    }

    private function getServerLoginCount(string $serverKey): int
    {
        $raw = Cache::get($this->serverLoginCacheKey($serverKey), 0);

        if (is_int($raw) || is_float($raw)) {
            return max(0, (int) $raw);
        }

        if (is_string($raw) && is_numeric($raw)) {
            return max(0, (int) $raw);
        }

        return 0;
    }

    private function increaseServerLoginCount(string $serverKey): void
    {
        $serverKey = trim($serverKey);
        if ($serverKey === '') {
            return;
        }

        $key = $this->serverLoginCacheKey($serverKey);
        $ttl = now()->addSeconds(self::SERVER_LOGIN_COUNTER_TTL_SECONDS);

        if (! Cache::has($key)) {
            Cache::put($key, 0, $ttl);
        }

        $nextCount = Cache::increment($key);
        if (is_int($nextCount) || is_float($nextCount)) {
            Cache::put($key, max(0, (int) $nextCount), $ttl);
            return;
        }

        $fallbackCount = $this->getServerLoginCount($serverKey) + 1;
        Cache::put($key, $fallbackCount, $ttl);
    }

    private function serverActiveMemberCacheKey(string $serverKey, string $memberId): string
    {
        return self::SERVER_ACTIVE_MEMBER_PREFIX . $serverKey . ':' . $memberId;
    }

    private function serverActiveIndexCacheKey(string $serverKey): string
    {
        return self::SERVER_ACTIVE_INDEX_PREFIX . $serverKey;
    }

    private function resolvePresenceMemberId(Request $request): string
    {
        $sessionId = trim((string) $request->session()->getId());
        $ipAddress = trim((string) $request->ip());
        $userAgent = strtolower(trim((string) $request->userAgent()));

        return sha1($sessionId . '|' . $ipAddress . '|' . $userAgent);
    }

    private function touchServerActivePresence(string $serverKey, Request $request): void
    {
        $serverKey = trim($serverKey);
        if ($serverKey === '') {
            return;
        }

        $memberId = $this->resolvePresenceMemberId($request);
        $memberKey = $this->serverActiveMemberCacheKey($serverKey, $memberId);
        $indexKey = $this->serverActiveIndexCacheKey($serverKey);
        $nowTs = now()->timestamp;

        Cache::put($memberKey, 1, now()->addSeconds(self::SERVER_ACTIVE_USER_TTL_SECONDS));

        $index = Cache::get($indexKey, []);
        if (! is_array($index)) {
            $index = [];
        }

        $index[$memberId] = $nowTs;
        Cache::forever($indexKey, $index);
    }

    private function getServerActiveUserCount(string $serverKey): int
    {
        $serverKey = trim($serverKey);
        if ($serverKey === '') {
            return 0;
        }

        $indexKey = $this->serverActiveIndexCacheKey($serverKey);
        $index = Cache::get($indexKey, []);

        if (! is_array($index) || $index === []) {
            return 0;
        }

        $activeCount = 0;
        $nowTs = now()->timestamp;
        $staleCutoffTs = $nowTs - (self::SERVER_ACTIVE_USER_TTL_SECONDS * 2);
        $prunedIndex = [];

        foreach ($index as $memberId => $lastSeenTs) {
            $memberId = trim((string) $memberId);
            if ($memberId === '') {
                continue;
            }

            if ((int) $lastSeenTs < $staleCutoffTs) {
                continue;
            }

            $memberKey = $this->serverActiveMemberCacheKey($serverKey, $memberId);
            if (Cache::has($memberKey)) {
                $activeCount++;
                $prunedIndex[$memberId] = (int) $lastSeenTs;
            }
        }

        Cache::forever($indexKey, $prunedIndex);

        return max(0, $activeCount);
    }

    private function getLoginIndicatorMeta(int $count, int $capacity): array
    {
        $capacity = max(1, $capacity);
        $ratio = $count / $capacity;

        if ($ratio >= 0.9) {
            return ['key' => 'high', 'label' => 'Tinggi'];
        }

        if ($ratio >= 0.7) {
            return ['key' => 'medium', 'label' => 'Sedang'];
        }

        return ['key' => 'low', 'label' => 'Rendah'];
    }

    private function isServerHighLoad(array $server): bool
    {
        return $this->serverLoadRatio($server) >= 0.9;
    }

    private function serverLoadRatio(array $server): float
    {
        $capacity = max(1, (int) ($server['capacity'] ?? 1));
        $activeUserCount = (int) ($server['active_user_count'] ?? -1);
        $loginCount = max(0, (int) ($server['login_count'] ?? 0));

        $currentLoadCount = $activeUserCount >= 0 ? max(0, $activeUserCount) : $loginCount;

        return $currentLoadCount / $capacity;
    }

    private function extractCountryCodeFromUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return '--';
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return '--';
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'IP';
            }

            return 'LAN';
        }

        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return 'INT';
        }

        $last = end($parts);
        if (is_string($last) && strlen($last) === 2 && ctype_alpha($last)) {
            return strtoupper($last);
        }

        return 'INT';
    }

    private function passwordMatches(string $plainPassword, string $hashFromDb): bool
    {
        if (Hash::check($plainPassword, $hashFromDb)) {
            return true;
        }

        return password_verify($plainPassword, $hashFromDb);
    }

    private function apiJson(array $payload)
    {
        $request = request();
        $appName = (string) ($this->getInfoFromGarudaCbt()->app_name ?? 'GARUDA CBT');

        if (! array_key_exists('app_name', $payload)) {
            $payload['app_name'] = $appName;
        }

        if (! array_key_exists('application_name', $payload)) {
            $payload['application_name'] = $appName;
        }

        if (! array_key_exists('nama_aplikasi', $payload)) {
            $payload['nama_aplikasi'] = $appName;
        }

        return response()
            ->json($payload)
            ->withHeaders($this->corsHeaders($request));
    }

    private function corsHeaders(Request $request): array
    {
        return [
            'Access-Control-Allow-Origin' => $this->allowedCorsOrigin($request),
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin',
        ];
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

    private function isExambroClient(Request $request): bool
    {
        return $this->matchesExambroUserAgent($request->userAgent());
    }

    private function isUserAgentDetectionEnabled(): bool
    {
        $raw = $this->readExambroSetting('user_agent_detection_enabled', 1);

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

    private function getExambroUserAgentPatterns(): array
    {
        $stored = (string) $this->readExambroSetting('user_agent_patterns', 'exambro');

        return $this->normalizeUserAgentPatterns($stored);
    }

    private function getExambroUserAgentPatternsAsText(): string
    {
        return implode("\n", $this->getExambroUserAgentPatterns());
    }

    private function normalizeUserAgentPatterns(string $raw): array
    {
        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $normalized = [];

        foreach ($parts as $part) {
            $value = strtolower(trim((string) $part));
            if ($value !== '') {
                $normalized[$value] = $value;
            }
        }

        return array_values($normalized);
    }

    private function serverMetaFilePath(): string
    {
        return storage_path('app/private/server-meta.json');
    }

    private function readServerMetaFromFile(): array
    {
        $path = $this->serverMetaFilePath();

        if (! File::exists($path)) {
            return [];
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function getConfiguredServers(): array
    {
        $mirrorsFromFile = $this->readMirrorListFromFile();
        if ($mirrorsFromFile !== []) {
            return $mirrorsFromFile;
        }

        $cached = $this->readPersistedSetting('cbt_servers_list', []);
        if (is_array($cached) && $cached !== []) {
            $normalized = $this->normalizeConfiguredServers($cached);
            $this->ensureMirrorListFileExists($normalized);

            return $normalized;
        }

        $meta = $this->readServerMetaFromFile();
        $serversFromFile = $meta['servers'] ?? [];
        if (is_array($serversFromFile) && $serversFromFile !== []) {
            $normalized = $this->normalizeConfiguredServers($serversFromFile);
            $this->writePersistedSetting('cbt_servers_list', $normalized);
            $this->ensureMirrorListFileExists($normalized);

            return $normalized;
        }

        return [];
    }

    private function normalizeConfiguredServers(array $servers): array
    {
        $normalized = [];
        $usedKeys = [];

        foreach (array_values($servers) as $index => $server) {
            if (! is_array($server)) {
                continue;
            }

            $rawKey = (string) ($server['key'] ?? '');
            $safeKey = $this->sanitizeServerKey($rawKey, $index + 1);

            if (isset($usedKeys[$safeKey])) {
                $safeKey .= '-' . ($index + 1);
            }

            $usedKeys[$safeKey] = true;

            $normalized[] = [
                'key' => $safeKey,
                'name' => trim((string) ($server['name'] ?? '')) ?: ('Server ' . ($index + 1)),
                'url' => trim((string) ($server['url'] ?? '')),
                'core' => max(1, (int) ($server['core'] ?? 4)),
                'ram' => trim((string) ($server['ram'] ?? '8 GB')) ?: '8 GB',
                'capacity' => max(1, (int) ($server['capacity'] ?? 40)),
                'hidden' => ((bool) ($server['hidden'] ?? false)) === true,
                'lb_enabled' => ((bool) ($server['lb_enabled'] ?? true)) === true,
                'selection_enabled' => ((bool) ($server['selection_enabled'] ?? true)) === true,
                'selection_disabled_until' => $this->normalizeSelectionDisabledUntil((string) ($server['selection_disabled_until'] ?? '')),
            ];
        }

        return $normalized;
    }

    private function persistConfiguredServers(array $servers): void
    {
        $normalized = $this->normalizeConfiguredServers($servers);
        $this->writePersistedSetting('cbt_servers_list', $normalized);
        $this->storeServerMetaToFile(['servers' => $normalized]);
        $this->writeMirrorListFile($normalized);
    }

    private function findServerIndexByKey(array $servers, string $key): int
    {
        foreach ($servers as $index => $server) {
            if ((string) ($server['key'] ?? '') === $key) {
                return $index;
            }
        }

        return -1;
    }

    private function sanitizeServerKey(string $rawKey, int $fallbackIndex): string
    {
        $normalized = Str::lower(trim($rawKey));
        $normalized = preg_replace('/[^a-z0-9\-]+/', '-', $normalized) ?: '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            return 'server-' . $fallbackIndex;
        }

        return $normalized;
    }

    private function generateUniqueServerKey(string $name, array $servers, int $fallbackIndex): string
    {
        $base = $this->sanitizeServerKey($name, $fallbackIndex);
        $existing = collect($servers)
            ->map(function ($server) {
                return (string) ($server['key'] ?? '');
            })
            ->filter()
            ->values()
            ->all();

        if (! in_array($base, $existing, true)) {
            return $base;
        }

        $counter = 2;
        while (in_array($base . '-' . $counter, $existing, true)) {
            $counter++;
        }

        return $base . '-' . $counter;
    }

    private function syncLegacyServerCachesFromConfiguredServers(array $servers): void
    {
        $serverOne = $servers[0] ?? null;
        $serverTwo = $servers[1] ?? null;
        $serverThree = $servers[2] ?? null;

        if (is_array($serverOne) && ! empty($serverOne['url'])) {
            DB::table('setting')->updateOrInsert(
                ['id_setting' => 1],
                ['web' => (string) $serverOne['url']]
            );

            $this->writePersistedSetting('cbt_server_name_primary', (string) ($serverOne['name'] ?? 'Server Utama'));
            $this->writePersistedSetting('cbt_server_spec_primary_core', (int) ($serverOne['core'] ?? 4));
            $this->writePersistedSetting('cbt_server_spec_primary_ram', (string) ($serverOne['ram'] ?? '8 GB'));
            $this->writePersistedSetting('cbt_server_capacity_primary', (int) ($serverOne['capacity'] ?? 40));
        }

        if (is_array($serverTwo)) {
            $this->writePersistedSetting('cbt_backup_url_1', (string) ($serverTwo['url'] ?? ''));
            $this->writePersistedSetting('cbt_server_name_backup_1', (string) ($serverTwo['name'] ?? 'Server 1'));
            $this->writePersistedSetting('cbt_server_spec_backup1_core', (int) ($serverTwo['core'] ?? 4));
            $this->writePersistedSetting('cbt_server_spec_backup1_ram', (string) ($serverTwo['ram'] ?? '8 GB'));
            $this->writePersistedSetting('cbt_server_capacity_backup1', (int) ($serverTwo['capacity'] ?? 40));
        }

        if (is_array($serverThree)) {
            $this->writePersistedSetting('cbt_backup_url_2', (string) ($serverThree['url'] ?? ''));
            $this->writePersistedSetting('cbt_server_name_backup_2', (string) ($serverThree['name'] ?? 'Server 2'));
            $this->writePersistedSetting('cbt_server_spec_backup2_core', (int) ($serverThree['core'] ?? 4));
            $this->writePersistedSetting('cbt_server_spec_backup2_ram', (string) ($serverThree['ram'] ?? '8 GB'));
            $this->writePersistedSetting('cbt_server_capacity_backup2', (int) ($serverThree['capacity'] ?? 40));
        }

        $this->invalidateInfoCaches();
    }

    private function countEligibleLoadBalancerServers(array $servers): int
    {
        return count(array_filter($servers, function ($server) {
            if (((bool) ($server['hidden'] ?? false)) === true) {
                return false;
            }

            $url = trim((string) ($server['url'] ?? ''));

            return $url !== '' && filter_var($url, FILTER_VALIDATE_URL);
        }));
    }

    private function mirrorListFilePath(): string
    {
        return storage_path('app/private/mirror_list.json');
    }

    private function versionFilePath(): string
    {
        return public_path('api/version.json');
    }

    private function readVersionPayloadFromFile(): array
    {
        $path = $this->versionFilePath();
        if (! File::exists($path)) {
            return [];
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeVersionPayloadToFile(array $payload): void
    {
        $path = $this->versionFilePath();
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function syncConfigJsonWithAdminSettings(): void
    {
        $configPath = public_path('api/config.json');
        $config = [];

        if (File::exists($configPath)) {
            $config = json_decode(File::get($configPath), true);
        }

        if (! is_array($config)) {
            $config = [];
        }

        $config['status_token'] = $this->isExambroActive();
        $config['status_pin'] = $this->isExambroPinActive();
        $config['warning_audio_enabled'] = $this->getExambroWarningValue() === 1;

        $version = (string) now()->timestamp;
        $config['version'] = $version;

        if (! File::isDirectory(dirname($configPath))) {
            File::makeDirectory(dirname($configPath), 0755, true);
        }

        File::put($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $versionPayload = [
            'config_version' => $version,
            'config_url' => '/api/config.json',
            'config_url_versioned' => '/api/config.json?v=' . rawurlencode($version),
            'last_updated' => now()->toIso8601String(),
            'timestamp' => now()->timestamp * 1000,
            'min_app_version' => trim((string) ($currentVersionPayload['min_app_version'] ?? '1.0.0')),
            'message' => trim((string) ($currentVersionPayload['message'] ?? 'Configuration updated via admin dashboard')),
        ];

        $this->writeVersionPayloadToFile($versionPayload);
    }

    private function getVersionSyncSettings(): array
    {
        $enabledRaw = $this->readPersistedSetting('version_sync_enabled', 0);
        $key = trim((string) $this->readPersistedSetting('version_sync_key', ''));
        $timeout = (int) $this->readPersistedSetting('version_sync_timeout_seconds', 2);

        return [
            'enabled' => $this->truthy($enabledRaw),
            'key' => $key,
            'timeout_seconds' => max(1, min($timeout, 5)),
        ];
    }

    private function getVersionSyncServers(): array
    {
        $stored = $this->readPersistedSetting('version_sync_servers', []);

        if (! is_array($stored)) {
            return [];
        }

        // If admin hasn't configured any version sync servers yet,
        // default the list to the configured mirror servers (preserve order).
        if ($stored === [] || $stored === null) {
            $mirrors = $this->readMirrorListFromFile();
            if (is_array($mirrors) && $mirrors !== []) {
                $mapped = [];
                foreach ($mirrors as $m) {
                    $mapped[] = [
                        'name' => (string) ($m['name'] ?? ($m['key'] ?? 'Server')),
                        'url' => (string) ($m['url'] ?? ''),
                    ];
                }

                return $this->normalizeVersionSyncServers($mapped);
            }
        }

        return $this->normalizeVersionSyncServers($stored);
    }

    private function collectVersionSyncTargets(array $syncServers): array
    {
        $request = request();
        $currentHost = $request instanceof Request ? strtolower((string) $request->getHost()) : '';
        $targets = [];

        foreach ($syncServers as $server) {
            if (! is_array($server)) {
                continue;
            }

            $url = trim((string) ($server['url'] ?? ''));
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '' || ($currentHost !== '' && strcasecmp($host, $currentHost) === 0)) {
                continue;
            }

            $baseUrl = rtrim($url, '/');

            $targets[$host] = [
                'host' => $host,
                'name' => trim((string) ($server['name'] ?? $host)) ?: $host,
                'base_url' => $baseUrl,
                'sync_endpoint' => $baseUrl . '/api/internal/version-sync',
            ];
        }

        return array_values($targets);
    }

    private function normalizeVersionSyncServers(array $servers): array
    {
        $normalized = [];

        foreach ($servers as $server) {
            if (! is_array($server)) {
                continue;
            }

            $url = trim((string) ($server['url'] ?? ''));
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '') {
                continue;
            }

            $normalized[$host] = [
                'name' => trim((string) ($server['name'] ?? $host)) ?: $host,
                'url' => rtrim($url, '/'),
            ];
        }

        return array_values($normalized);
    }

    private function parseVersionSyncServersText(string $rawText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
        $servers = [];

        foreach ($lines as $line) {
            $value = trim((string) $line);
            if ($value === '' || str_starts_with($value, '#')) {
                continue;
            }

            $name = '';
            $url = $value;

            if (str_contains($value, '|')) {
                [$namePart, $urlPart] = array_pad(explode('|', $value, 2), 2, '');
                $name = trim((string) $namePart);
                $url = trim((string) $urlPart);
            }

            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '') {
                continue;
            }

            $servers[$host] = [
                'name' => $name !== '' ? $name : $host,
                'url' => rtrim($url, '/'),
            ];
        }

        return array_values($servers);
    }

    private function exportVersionSyncServersAsText(array $servers): string
    {
        if ($servers === []) {
            return '';
        }

        $lines = [];

        foreach ($servers as $server) {
            if (! is_array($server)) {
                continue;
            }

            $name = trim((string) ($server['name'] ?? ''));
            $url = trim((string) ($server['url'] ?? ''));
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $lines[] = ($name !== '' ? $name : 'Server') . '|' . $url;
        }

        return implode("\n", $lines);
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'active', 'aktif'], true);
        }

        return false;
    }

    private function readMirrorListFromFile(): array
    {
        $path = $this->mirrorListFilePath();

        if (! File::exists($path)) {
            return [];
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $mirrors = $decoded;
        if (array_key_exists('mirrors', $decoded) && is_array($decoded['mirrors'])) {
            $mirrors = $decoded['mirrors'];
        }

        if (! is_array($mirrors) || $mirrors === []) {
            return [];
        }

        return $this->normalizeConfiguredServers($mirrors);
    }

    private function normalizeSelectionDisabledUntil(string $raw): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('c', $timestamp);
    }

    private function resolveServerSelectionRuntime(array $server): array
    {
        $isEnabled = ((bool) ($server['selection_enabled'] ?? true)) === true;
        $disabledUntil = $this->normalizeSelectionDisabledUntil((string) ($server['selection_disabled_until'] ?? ''));

        if ($disabledUntil === null) {
            return [
                'runtime_enabled' => $isEnabled,
                'timed_disabled' => false,
                'disabled_until' => null,
            ];
        }

        $untilTs = strtotime($disabledUntil);
        if ($untilTs === false) {
            return [
                'runtime_enabled' => $isEnabled,
                'timed_disabled' => false,
                'disabled_until' => null,
            ];
        }

        $timedDisabled = $untilTs > now()->timestamp;

        return [
            'runtime_enabled' => $isEnabled && ! $timedDisabled,
            'timed_disabled' => $timedDisabled,
            'disabled_until' => date('c', $untilTs),
        ];
    }

    private function ensureMirrorListFileExists(array $servers): void
    {
        if (File::exists($this->mirrorListFilePath())) {
            return;
        }

        $this->writeMirrorListFile($servers);
    }

    private function writeMirrorListFile(array $servers): void
    {
        $path = $this->mirrorListFilePath();
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $payload = $this->buildMirrorListPayload($servers);

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function buildMirrorListPayload(array $servers): array
    {
        $normalized = $this->normalizeConfiguredServers($servers);
        $eligibleMirrorCount = $this->countEligibleLoadBalancerServers($normalized);
        $defaultLb = $this->homepageUrl('/go-cbt');
        $configuredLb = (string) $this->readPersistedSetting('cbt_load_balancing_url', $defaultLb);
        $loadBalancingUrl = $eligibleMirrorCount > 1 ? $configuredLb : null;

        return [
            'generated_at' => now()->toIso8601String(),
            'load_balancing_enabled' => $eligibleMirrorCount > 1,
            'mirror_count' => $eligibleMirrorCount,
            'load_balancing_url' => $loadBalancingUrl,
            'mirrors' => array_map(function ($server) {
                $url = trim((string) ($server['url'] ?? ''));
                $isVisible = ((bool) ($server['hidden'] ?? false)) !== true;
                $lbEnabled = ((bool) ($server['lb_enabled'] ?? true)) === true;
                $selectionState = $this->resolveServerSelectionRuntime((array) $server);
                $runtimeState = $this->resolveRuntimeLoadBalancerState(
                    (string) ($server['key'] ?? ''),
                    $this->isServerUp($url),
                    $lbEnabled,
                    $isVisible,
                    $url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false
                );

                return [
                    'key' => (string) ($server['key'] ?? ''),
                    'name' => (string) ($server['name'] ?? ''),
                    'url' => $url,
                    'core' => max(1, (int) ($server['core'] ?? 4)),
                    'ram' => (string) ($server['ram'] ?? '8 GB'),
                    'capacity' => max(1, (int) ($server['capacity'] ?? 40)),
                    'hidden' => ! $isVisible,
                    'lb_enabled' => $lbEnabled,
                    'selection_enabled' => ((bool) ($server['selection_enabled'] ?? true)) === true,
                    'selection_runtime_enabled' => (bool) ($selectionState['runtime_enabled'] ?? true),
                    'selection_disabled_until' => $selectionState['disabled_until'] ?? null,
                    'lb_runtime_enabled' => (bool) ($runtimeState['runtime_enabled'] ?? false),
                    'auto_lb_excluded' => (bool) ($runtimeState['excluded'] ?? false),
                    'auto_lb_down_streak' => (int) ($runtimeState['down_streak'] ?? 0),
                    'auto_lb_disabled_until' => $runtimeState['disabled_until'] ?? null,
                    'load_balancer_candidate' => (bool) ($runtimeState['runtime_enabled'] ?? false) && (bool) ($selectionState['runtime_enabled'] ?? true),
                ];
            }, $normalized),
        ];
    }

    private function homepageUrl(string $path = ''): string
    {
        $request = request();
        $baseUrl = '';

        if ($request instanceof Request) {
            $host = trim((string) $request->getHost());
            if ($host !== '') {
                $baseUrl = $request->getSchemeAndHttpHost();
            }
        }

        if ($baseUrl === '') {
            $baseUrl = trim((string) config('app.url', ''));
        }

        if ($baseUrl === '' || ! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $baseUrl = url('/');
        }

        $baseUrl = rtrim($baseUrl, '/');
        $path = '/' . ltrim($path, '/');

        if ($path === '/') {
            return $baseUrl;
        }

        return $baseUrl . $path;
    }

    private function storeServerMetaToFile(array $updates): void
    {
        $path = $this->serverMetaFilePath();
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $current = $this->readServerMetaFromFile();
        $merged = array_merge($current, $updates, [
            'updated_at' => now()->toIso8601String(),
        ]);

        File::put($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Cache::forget(self::CACHE_KEY_CBT_INFO);
    }

    private function ensureWebSettingsTable(): bool
    {
        try {
            if (Schema::hasTable('web_settings')) {
                return true;
            }
        } catch (\Throwable $e) {
            // Lanjutkan ke upaya pembuatan tabel.
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

        $legacyCached = Cache::get($key, '__missing__');
        if ($legacyCached !== '__missing__') {
            $this->writePersistedSetting($key, $legacyCached);

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

        $this->cacheForeverSafely($cacheKey, $value);

        return $value;
    }

    private function writePersistedSetting(string $key, mixed $value): void
    {
        $cacheKey = 'web_setting:' . $key;
        $this->cacheForeverSafely($cacheKey, $value);
        $this->cacheForeverSafely($key, $value);
        $this->cacheForgetSafely(self::CACHE_KEY_CBT_INFO);

        if (! $this->ensureWebSettingsTable()) {
            return;
        }

        $now = now();
        $exists = DB::table('web_settings')->where('setting_key', $key)->exists();

        $payload = [
            'setting_value' => json_encode($value, JSON_UNESCAPED_SLASHES),
            'updated_at' => $now,
        ];

        if (! $exists) {
            $payload['created_at'] = $now;
        }

        DB::table('web_settings')->updateOrInsert(
            ['setting_key' => $key],
            $payload
        );
    }

    private function cacheForeverSafely(string $key, mixed $value): void
    {
        $this->prepareFileCacheDirectory($key);

        try {
            Cache::forever($key, $value);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist cache entry.', [
                'cache_key' => $key,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function cacheForgetSafely(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable $e) {
            Log::warning('Failed to forget cache entry.', [
                'cache_key' => $key,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function prepareFileCacheDirectory(string $key): void
    {
        if ((string) config('cache.default') !== 'file') {
            return;
        }

        $basePath = (string) config('cache.stores.file.path', '');
        if ($basePath === '') {
            return;
        }

        $hash = sha1($key);
        $directory = $basePath . '/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }
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

        foreach ($this->getExambroUserAgentPatterns() as $pattern) {
            if ($pattern !== '' && str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
