<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CbtInfoController extends Controller
{
    public function index()
    {
        $info = $this->getInfoFromGarudaCbt();
        $exambroActive = Cache::get('exambro_token_active', false);

        return view('cbt-info.index', compact('info', 'exambroActive'));
    }

    public function tokenInfo()
    {
        $info = $this->getInfoFromGarudaCbt();
        $exambroActive = Cache::get('exambro_token_active', false);

        return response()->json([
            'token' => $info->token,
            'exambro_active' => $exambroActive,
            'token_updated_at' => $info->token_updated_at,
            'token_valid_until' => $info->token_valid_until,
            'description' => $info->description,
            'cbt_url' => $info->cbt_url,
        ]);
    }

    public function admin()
    {
        if (! session('cbt_admin_auth')) {
            return redirect()->route('cbt.admin.login');
        }

        $info = $this->getInfoFromGarudaCbt();
        $exambroActive = Cache::get('exambro_token_active', false);
        $admin = (object) [
            'username' => session('cbt_admin_username'),
            'name' => session('cbt_admin_name'),
        ];

        return view('cbt-info.admin', compact('info', 'exambroActive', 'admin'));
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
            'cbt_url' => ['required', 'url', 'max:255'],
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
                'web' => $validated['cbt_url'],
                'sekolah' => 'GARUDA CBT',
                'nama_aplikasi' => 'GARUDA CBT',
            ]
        );

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
            'description' => $settingData->alamat ?? 'Silakan perbarui token dan URL CBT melalui halaman admin.',
            'school' => $settingData->sekolah ?? 'GARUDA CBT',
            'token_updated_at' => $tokenUpdatedAt ? now()->parse($tokenUpdatedAt)->format('d-m-Y H:i:s') : null,
            'token_valid_until' => $tokenValidUntil,
        ];
    }

    private function passwordMatches(string $plainPassword, string $hashFromDb): bool
    {
        if (Hash::check($plainPassword, $hashFromDb)) {
            return true;
        }

        return password_verify($plainPassword, $hashFromDb);
    }
}
