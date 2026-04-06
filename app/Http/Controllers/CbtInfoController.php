<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use ZipArchive;

class CbtInfoController extends Controller
{
    private const SERVER_LOGIN_COUNTER_PREFIX = 'server_login_count:';
    private const SERVER_ACTIVE_MEMBER_PREFIX = 'server_active_member:';
    private const SERVER_ACTIVE_INDEX_PREFIX = 'server_active_index:';
    private const SERVER_ACTIVE_USER_TTL_SECONDS = 120;
    private const SERVER_LOGIN_COUNTER_TTL_SECONDS = 7200;
    private const DB_REFRESH_INTERVAL_SECONDS = 300;
    private const SERVER_STATUS_UP_TTL_SECONDS = 300;
    private const SERVER_STATUS_DOWN_TTL_SECONDS = 120;
    private const CACHE_KEY_CBT_INFO = 'cbt_info_payload';
    private const CACHE_KEY_CBT_TOKEN_ROW = 'cbt_token_row:id:1';
    private const CACHE_KEY_SETTING_ROW = 'setting_row:id:1';

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

        if ($this->isServerHighLoad($server)) {
            return redirect()->route('cbt.lb');
        }

        $this->increaseServerLoginCount((string) $server['key']);
        $this->touchServerActivePresence((string) $server['key'], $request);

        return redirect()->away($targetUrl);
    }

    public function index(Request $request)
    {
        if ($this->isExambroClient($request)) {
            return redirect()->route('cbt.exambro.page');
        }

        $info = $this->getInfoFromGarudaCbt();
        $exambroActive = $this->isExambroActive();
        $servers = $this->attachCachedQrCodes($this->buildServerList($info));

        return view('cbt-info.index', compact('info', 'exambroActive', 'servers'));
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
        $info = $this->getInfoFromGarudaCbt();
        $exambroActive = $this->isExambroActive();
        $pinActive = $this->isExambroPinActive();
        $servers = $this->buildServerList($info);

        return $this->apiJson([
            'token' => $info->cbt_token,
            'cbt_token' => $info->cbt_token,
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
            'token_soal'        => $info->cbt_token,
            'cbt_token'         => $info->cbt_token,
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
                return [
                    'key' => $server['key'] ?? null,
                    'name' => $server['name'] ?? null,
                    'url' => $server['url'] ?? null,
                    'status' => ($server['is_up'] ?? false) ? 'up' : 'down',
                    'selectable' => (($server['is_up'] ?? false) === true) && ! empty($server['url']),
                    'country_code' => $server['country_code'] ?? '--',
                    'core' => (int) ($server['core'] ?? 0),
                    'ram' => (string) ($server['ram'] ?? ''),
                    'capacity' => (int) ($server['capacity'] ?? 40),
                    'login_count' => (int) ($server['login_count'] ?? 0),
                    'active_user_count' => (int) ($server['active_user_count'] ?? 0),
                    'active_user_ttl_seconds' => self::SERVER_ACTIVE_USER_TTL_SECONDS,
                    'login_indicator' => $server['login_indicator'] ?? 'low',
                    'login_indicator_label' => $server['login_indicator_label'] ?? 'Rendah',
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
            return (($server['is_up'] ?? false) === true) && ! empty($server['url']);
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
        $exambroEmergencyExitPin = $this->getExambroEmergencyExitPin();
        $exambroEmergencyExitPinSource = $this->hasPersistedExambroEmergencyExitPin() ? 'database' : 'env';
        $userAgentDetectionEnabled = $this->isUserAgentDetectionEnabled();
        $userAgentPatterns = $this->getExambroUserAgentPatternsAsText();
        $exambroPageUrl      = route('cbt.exambro.page');
        $exambroApiUrl       = route('cbt.exambro.info');

        return view('cbt-info.admin', compact(
            'info',
            'exambroActive',
            'admin',
            'servers',
            'exambroToken',
            'exambroTokenSource',
            'exambroEmergencyExitPin',
            'exambroEmergencyExitPinSource',
            'userAgentDetectionEnabled',
            'userAgentPatterns',
            'exambroWarningValue',
            'exambroTokenVisibleOnPage',
                        'exambroPinActive',
            'exambroPageUrl',
            'exambroApiUrl'
        ));
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

        $this->writePersistedSetting('exambro_user_agent_detection_enabled', $enabled ? 1 : 0);
        $this->writePersistedSetting('exambro_user_agent_patterns', implode("\n", $patterns));

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
        $this->writePersistedSetting('exambro_exit_emergency_pin', $pin);

        return redirect('/admin/cbt-info#panel-token-pin')
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
        ]);

        DB::table('cbt_token')->updateOrInsert(
            ['id_token' => 1],
            [
                'token' => strtoupper($validated['token']),
                'auto' => 0,
                'jarak' => 0,
                'updated' => now()->format('Y-m-d H:i:s'),
            ]
        );

        if (! empty($validated['primary_url'])) {
            DB::table('setting')->updateOrInsert(
                ['id_setting' => 1],
                [
                    'web' => $validated['primary_url'],
                    'sekolah' => 'GARUDA CBT',
                    'nama_aplikasi' => 'GARUDA CBT',
                ]
            );
        }

        if (! empty($validated['backup_url_1'])) {
            $this->writePersistedSetting('cbt_backup_url_1', $validated['backup_url_1']);
        }

        if (! empty($validated['backup_url_2'])) {
            $this->writePersistedSetting('cbt_backup_url_2', $validated['backup_url_2']);
        }

        if ($request->has('server_name_primary')) {
            $this->writePersistedSetting('cbt_server_name_primary', trim((string) ($validated['server_name_primary'] ?? '')) ?: 'Server Utama');
        }

        if ($request->has('server_name_backup_1')) {
            $this->writePersistedSetting('cbt_server_name_backup_1', trim((string) ($validated['server_name_backup_1'] ?? '')) ?: 'Server 1');
        }

        if ($request->has('server_name_backup_2')) {
            $this->writePersistedSetting('cbt_server_name_backup_2', trim((string) ($validated['server_name_backup_2'] ?? '')) ?: 'Server 2');
        }

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

        $this->invalidateInfoCaches();

        return redirect()->route('cbt.admin')->with('status', 'Informasi CBT berhasil diperbarui.');
    }

    public function toggleExambro(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentStatus = $this->isExambroActive();
        $this->writePersistedSetting('exambro_token_active', ! $currentStatus);

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

        return redirect()->route('cbt.admin')->with('status', 'PIN Exambro berhasil digenerate dan dipisahkan dari token soal.');
    }

    public function toggleExambroWarning(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentValue = $this->getExambroWarningValue();
        $nextValue = $currentValue === 1 ? 0 : 1;

        $this->writePersistedSetting('exambro_warning_active', $nextValue);

        return redirect()->route('cbt.admin')->with('status', 'Pengaturan peringatan Exambro diubah menjadi ' . ($nextValue === 1 ? 'ON (1)' : 'OFF (0)') . '.');
    }

    public function toggleExambroTokenVisibilityForPage(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentValue = $this->isExambroTokenVisibleOnPage();
        $nextValue = $currentValue ? 0 : 1;

        $this->writePersistedSetting('exambro_show_pin_on_page', $nextValue);

        return redirect()->route('cbt.admin')->with('status', 'Tampilan PIN Exambro di halaman Exambro diubah menjadi ' . ($nextValue === 1 ? 'TAMPIL' : 'SEMBUNYI') . '.');
    }

    public function toggleExambroPinStatus(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentStatus = $this->isExambroPinActive();
        $this->writePersistedSetting('exambro_pin_active', ! $currentStatus);

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
        $raw = $this->readPersistedSetting('exambro_token_active', false);

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
        $raw = $this->readPersistedSetting('exambro_pin_active', true);

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
        $persisted = trim((string) $this->readPersistedSetting('exambro_exit_emergency_pin', ''));
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
        return trim((string) $this->readPersistedSetting('exambro_exit_emergency_pin', '')) !== '';
    }

    private function getExambroToken(): string
    {
        $persistedToken = strtoupper(trim((string) $this->readPersistedSetting('exambro_token', '')));
        if ($persistedToken !== '') {
            return $persistedToken;
        }

        $fileToken = $this->readExambroTokenFromFile();
        if ($fileToken !== '') {
            $this->writePersistedSetting('exambro_token', $fileToken);

            return $fileToken;
        }

        return strtoupper((string) config('app.exambro_token_pin', ''));
    }

    private function getExambroTokenSource(): string
    {
        if (trim((string) $this->readPersistedSetting('exambro_token', '')) !== '') {
            return 'db';
        }

        return $this->readExambroTokenFromFile() !== '' ? 'web' : 'env';
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
        $this->writePersistedSetting('exambro_token', strtoupper(trim($token)));

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

    private function getExambroWarningValue(): int
    {
        $raw = $this->readPersistedSetting('exambro_warning_active', 1);

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
        $raw = $this->readPersistedSetting('exambro_show_pin_on_page', 1);

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
            $loginCount = $this->getServerLoginCount((string) ($server['key'] ?? ''));
            $activeUserCount = $this->getServerActiveUserCount((string) ($server['key'] ?? ''));
            $indicatorMeta = $this->getLoginIndicatorMeta($activeUserCount, (int) ($server['capacity'] ?? 40));
            $isHidden = ((bool) ($server['hidden'] ?? false)) === true;

            $server['is_up'] = $isUp;
            $server['status_label'] = $isUp ? 'SERVER UP' : 'SERVER DOWN';
            $server['status_class'] = $isUp ? 'up' : 'down';
            $server['hidden'] = $isHidden;
            $server['country_code'] = $this->extractCountryCodeFromUrl($server['url']);
            $server['login_count'] = $loginCount;
            $server['active_user_count'] = $activeUserCount;
            $server['login_indicator'] = $indicatorMeta['key'];
            $server['login_indicator_label'] = $indicatorMeta['label'];
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
        foreach ($servers as &$server) {
            $url = trim((string) ($server['url'] ?? ''));
            $server['qr_svg'] = null;

            if ((($server['is_up'] ?? false) !== true) || $url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $cacheKey = 'server_qr_svg:' . sha1($url);
            $server['qr_svg'] = Cache::remember($cacheKey, now()->addDay(), function () use ($url) {
                return QrCode::format('svg')
                    ->size(200)
                    ->margin(1)
                    ->generate($url);
            });
        }
        unset($server);

        return $servers;
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
            ],
            [
                'key' => 'backup1',
                'name' => $info->server_name_backup_1,
                'url' => $info->cbt_backup_url_1,
                'core' => max(1, (int) ($info->server_backup1_core ?? 4)),
                'ram' => (string) ($info->server_backup1_ram ?? '8 GB'),
                'capacity' => max(1, (int) ($info->server_backup1_capacity ?? 40)),
                'hidden' => false,
            ],
            [
                'key' => 'backup2',
                'name' => $info->server_name_backup_2,
                'url' => $info->cbt_backup_url_2,
                'core' => max(1, (int) ($info->server_backup2_core ?? 4)),
                'ram' => (string) ($info->server_backup2_ram ?? '8 GB'),
                'capacity' => max(1, (int) ($info->server_backup2_capacity ?? 40)),
                'hidden' => false,
            ],
        ];
    }

    private function selectAvailableServer(array $servers): array
    {
        $upServers = array_values(array_filter($servers, function ($server) {
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

        $serversWithUrl = array_values(array_filter($servers, function ($server) {
            return ! empty($server['url']);
        }));

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

        $cacheKey = 'server_up_status:' . sha1($url);
        $cached = Cache::get($cacheKey, null);
        if (is_bool($cached)) {
            return $cached;
        }

        $isUp = false;

        try {
            $response = Http::connectTimeout(1)
                ->timeout(3)
                ->withOptions(['allow_redirects' => true, 'http_errors' => false])
                ->get($url);

            $isUp = $response->successful() || $response->redirect();
        } catch (\Throwable $e) {
            $isUp = false;
        }

        Cache::put(
            $cacheKey,
            $isUp,
            now()->addSeconds($isUp ? self::SERVER_STATUS_UP_TTL_SECONDS : self::SERVER_STATUS_DOWN_TTL_SECONDS)
        );

        return $isUp;
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

    private function getExambroUserAgentPatterns(): array
    {
        $stored = (string) $this->readPersistedSetting('exambro_user_agent_patterns', 'exambro');

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
        $cached = $this->readPersistedSetting('cbt_servers_list', []);
        if (is_array($cached) && $cached !== []) {
            return $this->normalizeConfiguredServers($cached);
        }

        $meta = $this->readServerMetaFromFile();
        $serversFromFile = $meta['servers'] ?? [];
        if (is_array($serversFromFile) && $serversFromFile !== []) {
            $normalized = $this->normalizeConfiguredServers($serversFromFile);
            $this->writePersistedSetting('cbt_servers_list', $normalized);

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
            ];
        }

        return $normalized;
    }

    private function persistConfiguredServers(array $servers): void
    {
        $normalized = $this->normalizeConfiguredServers($servers);
        $this->writePersistedSetting('cbt_servers_list', $normalized);
        $this->storeServerMetaToFile(['servers' => $normalized]);
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

        Cache::forever($cacheKey, $value);

        return $value;
    }

    private function writePersistedSetting(string $key, mixed $value): void
    {
        $cacheKey = 'web_setting:' . $key;
        Cache::forever($cacheKey, $value);
        Cache::forever($key, $value);
        Cache::forget(self::CACHE_KEY_CBT_INFO);

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
