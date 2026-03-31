<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class CbtInfoController extends Controller
{
    public function index()
    {
        $info = $this->getInfoFromGarudaCbt();
        $exambroActive = Cache::get('exambro_token_active', false);
        $servers = $this->buildServerList($info);

        return view('cbt-info.index', compact('info', 'exambroActive', 'servers'));
    }

    public function exambroPage()
    {
        return view('cbt-info.exambro');
    }

    public function tokenInfo()
    {
        $info = $this->getInfoFromGarudaCbt();
        $exambroActive = Cache::get('exambro_token_active', false);
        $servers = $this->buildServerList($info);

        return $this->apiJson([
            'token' => $info->token,
            'exambro_active' => $exambroActive,
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
        $exambroActive  = Cache::get('exambro_token_active', false);
        $servers        = $this->buildServerList($info);
        $serverMap      = collect($servers)->keyBy('key');
        $recommended    = collect($servers)->first(function ($server) {
            return ($server['is_up'] ?? false) === true && ! empty($server['url']);
        });

        $primary = $serverMap->get('primary', []);
        $backup1 = $serverMap->get('backup1', []);
        $backup2 = $serverMap->get('backup2', []);

        return $this->apiJson([
            /* ── Informasi Token ────────────────────────────── */
            'status'            => 'ok',
            'token'             => $info->token,
            'exambro_active'    => $exambroActive,
            'token_status'      => $exambroActive ? 'active' : 'inactive',
            'token_updated_at'  => $info->token_updated_at,
            'token_valid_until' => $info->token_valid_until,

            /* ── Informasi Sekolah ──────────────────────────── */
            'school'            => $info->school,
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
                    'selectable' => $exambroActive && (($server['is_up'] ?? false) === true) && ! empty($server['url']),
                ];
            })->values(),

            'checked_at'        => now()->toIso8601String(),
        ]);
    }

    public function admin()
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $info = $this->getInfoFromGarudaCbt();
        $servers = $this->buildServerList($info);
        $exambroActive = Cache::get('exambro_token_active', false);
        $admin = (object) [
            'username' => session('cbt_admin_username'),
            'name' => session('cbt_admin_name'),
        ];

        $exambroApiKey = (string) config('app.exambro_api_key', '');
        $exambroPageUrl = route('cbt.exambro.page', ['key' => $exambroApiKey]);
        $exambroApiUrl = route('cbt.exambro.info', ['key' => $exambroApiKey]);

        return view('cbt-info.admin', compact(
            'info',
            'exambroActive',
            'admin',
            'servers',
            'exambroApiKey',
            'exambroPageUrl',
            'exambroApiUrl'
        ));
    }

    public function showLogin()
    {
        if (session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin');
        }

        return view('cbt-info.login');
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

        $currentStatus = Cache::get('exambro_token_active', false);
        Cache::forever('exambro_token_active', ! $currentStatus);

        $statusLabel = ! $currentStatus ? 'AKTIF' : 'NON-AKTIF';

        return redirect()->route('cbt.admin')->with('status', "Status token Exambro diubah menjadi {$statusLabel}.");
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
            'cbt_url' => $settingData->web ?? config('app.url'),
            'cbt_backup_url_1' => Cache::get('cbt_backup_url_1', $settingData->web ?? config('app.url')),
            'cbt_backup_url_2' => Cache::get('cbt_backup_url_2', $settingData->web ?? config('app.url')),
            'description' => $settingData->alamat ?? 'Silakan perbarui token dan URL CBT melalui halaman admin.',
            'school' => $settingData->sekolah ?? 'GARUDA CBT',
            'token_updated_at' => $tokenUpdatedAt ? now()->parse($tokenUpdatedAt)->format('d-m-Y H:i:s') : null,
            'token_valid_until' => $tokenValidUntil,
        ];
    }

    private function buildServerList(object $info): array
    {
        $servers = [
            [
                'key' => 'primary',
                'name' => 'URL Utama',
                'url' => $info->cbt_url,
            ],
            [
                'key' => 'backup1',
                'name' => 'URL Backup 1',
                'url' => $info->cbt_backup_url_1,
            ],
            [
                'key' => 'backup2',
                'name' => 'URL Backup 2',
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
        return response()
            ->json($payload)
            ->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin, X-Exambro-Key',
            ]);
    }
}
