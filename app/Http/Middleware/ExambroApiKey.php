<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        $cachedKey = (string) Cache::get('exambro_api_key', '');
        $validKey  = $cachedKey !== '' ? $cachedKey : (string) config('app.exambro_api_key', '');

        // Ambil key dari header X-Exambro-Key atau query param ?key=
        $provided = trim((string) ($request->header('X-Exambro-Key') ?? $request->query('key', '')));

        $keyValid = $validKey !== '' && hash_equals($validKey, $provided);

        // 2. API key cocok → diizinkan
        if ($keyValid) {
            return $next($request);
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
}
