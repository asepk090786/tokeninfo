<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CbtInfoController extends Controller
{
    public function index()
    {
        $info = $this->getInfoFromGarudaCbt();
        $exambroActive = $this->isExambroActive();
        $servers = $this->buildServerList($info);

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
            return response('', 204)->withHeaders([
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin, X-Exambro-Key',
            ]);
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

        $primary = $serverMap->get('primary', []);
        $backup1 = $serverMap->get('backup1', []);
        $backup2 = $serverMap->get('backup2', []);
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

            /* ── Server Backup 1 ────────────────────────────── */
            'server_backup1'        => $backup1['url'] ?? null,
            'server_backup1_status' => ($backup1['is_up'] ?? false) ? 'up' : 'down',

            /* ── Server Backup 2 ────────────────────────────── */
            'server_backup2'        => $backup2['url'] ?? null,
            'server_backup2_status' => ($backup2['is_up'] ?? false) ? 'up' : 'down',

            /* ── Server Rekomendasi (pertama yang UP) ───────── */
            'server_recommended'    => $recommended['url'] ?? null,

            'servers' => collect($servers)->map(function ($server) use ($exambroActive) {
                return [
                    'key' => $server['key'] ?? null,
                    'name' => $server['name'] ?? null,
                    'url' => $server['url'] ?? null,
                    'status' => ($server['is_up'] ?? false) ? 'up' : 'down',
                    'selectable' => (($server['is_up'] ?? false) === true) && ! empty($server['url']),
                ];
            })->values(),

            'checked_at'        => now()->toIso8601String(),
        ]);
    }

    public function exambroTokenStatus(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders([
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin, X-Exambro-Key',
            ]);
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
            return response('', 204)->withHeaders([
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin, X-Exambro-Key',
            ]);
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

    public function admin()
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $info = $this->getInfoFromGarudaCbt();
        $servers = $this->buildServerList($info);
        $exambroActive = $this->isExambroActive();
        $exambroWarningValue = $this->getExambroWarningValue();
        $exambroTokenVisibleOnPage = $this->isExambroTokenVisibleOnPage();
            $exambroPinActive = $this->isExambroPinActive();
        $admin = (object) [
            'username' => session('cbt_admin_username'),
            'name' => session('cbt_admin_name'),
        ];

        $exambroApiKey       = $this->getExambroApiKey();
        $exambroApiKeySource = (string) Cache::get('exambro_api_key', '') !== '' ? 'generated' : 'env';
        $exambroToken        = $this->getExambroToken();
        $exambroTokenSource  = $this->getExambroTokenSource();
        $exambroPageUrl      = route('cbt.exambro.page', ['key' => $exambroApiKey]);
        $exambroApiUrl       = route('cbt.exambro.info', ['key' => $exambroApiKey]);
        $exambroConfigDownloadUrl = route('cbt.exambro.api-key.download.app', ['key' => $exambroApiKey]);

        return view('cbt-info.admin', compact(
            'info',
            'exambroActive',
            'admin',
            'servers',
            'exambroApiKey',
            'exambroApiKeySource',
            'exambroToken',
            'exambroTokenSource',
            'exambroWarningValue',
            'exambroTokenVisibleOnPage',
                        'exambroPinActive',
            'exambroPageUrl',
            'exambroApiUrl',
            'exambroConfigDownloadUrl'
        ));
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

    public function update(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:6'],
            'primary_url' => ['required', 'url', 'max:255'],
            'backup_url_1' => ['required', 'url', 'max:255'],
            'backup_url_2' => ['required', 'url', 'max:255'],
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

        DB::table('setting')->updateOrInsert(
            ['id_setting' => 1],
            [
                'web' => $validated['primary_url'],
                'sekolah' => 'GARUDA CBT',
                'nama_aplikasi' => 'GARUDA CBT',
            ]
        );

        Cache::forever('cbt_backup_url_1', $validated['backup_url_1']);
        Cache::forever('cbt_backup_url_2', $validated['backup_url_2']);

        if (! empty($validated['description'])) {
            DB::table('setting')->where('id_setting', 1)->update([
                'alamat' => $validated['description'],
            ]);
        }

        return redirect()->route('cbt.admin')->with('status', 'Informasi CBT berhasil diperbarui.');
    }

    public function toggleExambro(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentStatus = $this->isExambroActive();
        Cache::forever('exambro_token_active', ! $currentStatus);

        $statusLabel = ! $currentStatus ? 'AKTIF' : 'NON-AKTIF';

        return redirect()->route('cbt.admin')->with('status', "Status token Exambro diubah menjadi {$statusLabel}.");
    }

    public function generateExambroApiKey(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        try {
            $newKey = 'exb_' . bin2hex(random_bytes(24));
        } catch (\Throwable $e) {
            $newKey = 'exb_' . Str::lower(Str::random(48));
        }

        Cache::forever('exambro_api_key', $newKey);

        return redirect()->route('cbt.admin')->with('status', 'API key Exambro berhasil digenerate ulang.');
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

        Cache::forever('exambro_warning_active', $nextValue);

        return redirect()->route('cbt.admin')->with('status', 'Pengaturan peringatan Exambro diubah menjadi ' . ($nextValue === 1 ? 'ON (1)' : 'OFF (0)') . '.');
    }

    public function toggleExambroTokenVisibilityForPage(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentValue = $this->isExambroTokenVisibleOnPage();
        $nextValue = $currentValue ? 0 : 1;

        Cache::forever('exambro_show_pin_on_page', $nextValue);

        return redirect()->route('cbt.admin')->with('status', 'Tampilan PIN Exambro di halaman Exambro diubah menjadi ' . ($nextValue === 1 ? 'TAMPIL' : 'SEMBUNYI') . '.');
    }

    public function toggleExambroPinStatus(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $currentStatus = $this->isExambroPinActive();
        Cache::forever('exambro_pin_active', ! $currentStatus);

        $statusLabel = ! $currentStatus ? 'AKTIF' : 'NON-AKTIF';

        return redirect()->route('cbt.admin')->with('status', "Status PIN Exambro diubah menjadi {$statusLabel}.");
    }

    public function downloadExambroApiConfig(Request $request)
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $apiKey  = $this->getExambroApiKey();
        $pageUrl = route('cbt.exambro.page', ['key' => $apiKey]);

        $payload = [
            'api_key'          => $apiKey,
            'exambro_page_url' => $pageUrl,
            'exambro_token'    => $this->getExambroToken(),
            'status_pin'       => $this->isExambroPinActive() ? 1 : 0,
            'school'           => $this->getInfoFromGarudaCbt()->school,
            'app_name'         => $this->getInfoFromGarudaCbt()->app_name,
            'application_name' => $this->getInfoFromGarudaCbt()->app_name,
            'nama_aplikasi'    => $this->getInfoFromGarudaCbt()->app_name,
        ];

        $fileName = 'exambro-api-config-' . now()->format('Ymd-His') . '.json';
        $json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type'        => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    public function downloadExambroApiConfigForApp(Request $request)
    {
        $apiKey  = $this->getExambroApiKey();
        $pageUrl = route('cbt.exambro.page', ['key' => $apiKey]);

        $payload = [
            'api_key'          => $apiKey,
            'exambro_page_url' => $pageUrl,
            'exambro_token'    => $this->getExambroToken(),
            'status_pin'       => $this->isExambroPinActive() ? 1 : 0,
            'school'           => $this->getInfoFromGarudaCbt()->school,
            'app_name'         => $this->getInfoFromGarudaCbt()->app_name,
            'application_name' => $this->getInfoFromGarudaCbt()->app_name,
            'nama_aplikasi'    => $this->getInfoFromGarudaCbt()->app_name,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type'        => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="config.json"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    private function isExambroActive(): bool
    {
        $raw = Cache::get('exambro_token_active', false);

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
        $raw = Cache::get('exambro_pin_active', true);

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

    private function getExambroApiKey(): string
    {
        // Cache adalah sumber utama (hasil generate dari panel admin)
        $cachedKey = (string) Cache::get('exambro_api_key', '');
        if ($cachedKey !== '') {
            return $cachedKey;
        }

        // Fallback ke .env hanya jika belum pernah generate
        return (string) config('app.exambro_api_key', '');
    }

    private function getExambroToken(): string
    {
        $fileToken = $this->readExambroTokenFromFile();
        if ($fileToken !== '') {
            return $fileToken;
        }

        return strtoupper((string) config('app.exambro_token_pin', ''));
    }

    private function getExambroTokenSource(): string
    {
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

    public function activeExambroApiKey(): string
    {
        return $this->getExambroApiKey();
    }

    private function getExambroWarningValue(): int
    {
        $raw = Cache::get('exambro_warning_active', 1);

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
        $raw = Cache::get('exambro_show_pin_on_page', 1);

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
        $tokenData = DB::table('cbt_token')->where('id_token', 1)->first();
        $settingData = DB::table('setting')->where('id_setting', 1)->first();

        $tokenUpdatedAt = $tokenData->updated ?? null;
        $tokenLifetimeMinutes = isset($tokenData->jarak) ? (int) $tokenData->jarak : 0;

        $tokenValidUntil = null;
        if (! empty($tokenUpdatedAt) && $tokenLifetimeMinutes > 0) {
            $tokenValidUntil = now()->parse($tokenUpdatedAt)->addMinutes($tokenLifetimeMinutes)->format('d-m-Y H:i:s');
        }

        return (object) [
            'token' => $tokenData->token ?? 'BELUM-DISET',
            'cbt_token' => $tokenData->token ?? 'BELUM-DISET',
            'exambro_token' => $this->getExambroToken(),
            'cbt_url' => $settingData->web ?? config('app.url'),
            'cbt_backup_url_1' => Cache::get('cbt_backup_url_1', $settingData->web ?? config('app.url')),
            'cbt_backup_url_2' => Cache::get('cbt_backup_url_2', $settingData->web ?? config('app.url')),
            'description' => $settingData->alamat ?? 'Silakan perbarui token dan URL CBT melalui halaman admin.',
            'school' => $settingData->sekolah ?? 'GARUDA CBT',
            'app_name' => $settingData->nama_aplikasi ?? 'GARUDA CBT',
            'token_updated_at' => $tokenUpdatedAt ? now()->parse($tokenUpdatedAt)->format('d-m-Y H:i:s') : null,
            'token_valid_until' => $tokenValidUntil,
        ];
    }

    private function buildServerList(object $info): array
    {
        $servers = [
            [
                'key' => 'primary',
                'name' => 'Server Utama',
                'url' => $info->cbt_url,
            ],
            [
                'key' => 'backup1',
                'name' => 'Server 1',
                'url' => $info->cbt_backup_url_1,
            ],
            [
                'key' => 'backup2',
                'name' => 'Server 2',
                'url' => $info->cbt_backup_url_2,
            ],
        ];

        foreach ($servers as &$server) {
            $isUp = $this->isServerUp($server['url']);
            $server['is_up'] = $isUp;
            $server['status_label'] = $isUp ? 'SERVER UP' : 'SERVER DOWN';
            $server['status_class'] = $isUp ? 'up' : 'down';
            $server['qr_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($server['url']);
        }
        unset($server);

        return $servers;
    }

    private function selectAvailableServer(array $servers): array
    {
        foreach ($servers as $server) {
            if (($server['is_up'] ?? false) === true && ! empty($server['url'])) {
                return $server;
            }
        }

        foreach ($servers as $server) {
            if (! empty($server['url'])) {
                return $server;
            }
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
        if (empty($url)) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->withOptions(['allow_redirects' => true])
                ->get($url);

            return $response->successful() || $response->redirect();
        } catch (\Throwable $e) {
            return false;
        }
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
            ->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin, X-Exambro-Key',
            ]);
    }
}
