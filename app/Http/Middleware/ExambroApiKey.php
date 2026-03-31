<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

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

        if ($request->session()->get('cbt_admin_auth') === true) {
            return $next($request);
        }

        $validKey = config('app.exambro_api_key');
        $allowedUserAgentKeywords = [
            'exambrowser',
            'exambro',
        ];

        // Ambil key dari header X-Exambro-Key atau query param ?key=
        $provided = $request->header('X-Exambro-Key')
            ?? $request->query('key');

        $userAgent = strtolower((string) $request->userAgent());
        $isExamBrowserClient = false;

        foreach ($allowedUserAgentKeywords as $keyword) {
            if (str_contains($userAgent, $keyword)) {
                $isExamBrowserClient = true;
                break;
            }
        }

        if (empty($validKey) || ! hash_equals($validKey, (string) $provided)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Sertakan API key yang valid.',
            ], 401)->withHeaders([
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin, X-Exambro-Key',
            ]);
        }

        if (! $isExamBrowserClient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. Endpoint ini hanya untuk aplikasi ExamBrowser.',
            ], 403)->withHeaders([
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin, X-Exambro-Key',
            ]);
        }

        return $next($request);
    }
}
