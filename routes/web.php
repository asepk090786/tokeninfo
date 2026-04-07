<?php

use App\Http\Controllers\CbtInfoController;
use App\Http\Controllers\ConfigApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [CbtInfoController::class, 'index'])->name('cbt.index');
Route::get('/go-cbt', [CbtInfoController::class, 'loadBalancer'])->name('cbt.lb');
Route::get('/info', [CbtInfoController::class, 'index']);
Route::get('/exambro', [CbtInfoController::class, 'exambroPage'])
    ->middleware('exambro.key')
    ->name('cbt.exambro.page');
Route::get('/exambro/connect/{serverKey}', [CbtInfoController::class, 'connectServer'])
    ->middleware('exambro.key')
    ->name('cbt.exambro.connect');
Route::get('/api/token-info', [CbtInfoController::class, 'tokenInfo'])->name('cbt.token.info');
Route::match(['GET', 'OPTIONS'], '/api/exambro-info', [CbtInfoController::class, 'exambroInfo'])
    ->middleware(['throttle:exambro-api', 'exambro.key'])
    ->name('cbt.exambro.info');
Route::match(['POST', 'OPTIONS'], '/api/server-presence/heartbeat', [CbtInfoController::class, 'heartbeatServerPresence'])
    ->middleware(['throttle:presence-heartbeat', 'exambro.key'])
    ->name('cbt.server.presence.heartbeat');
Route::match(['GET', 'OPTIONS'], '/api/exambro-status/token', [CbtInfoController::class, 'exambroTokenStatus'])
    ->middleware(['throttle:exambro-api', 'exambro.key'])
    ->name('cbt.exambro.status.token');
Route::match(['GET', 'OPTIONS'], '/api/exambro-status/peringatan', [CbtInfoController::class, 'exambroWarningStatus'])
    ->middleware(['throttle:exambro-api', 'exambro.key'])
    ->name('cbt.exambro.status.warning');

// NEW: Batch endpoint untuk mengurangi request count - combines 3 endpoints to 1
Route::match(['GET', 'OPTIONS'], '/api/exambro-full-status', [CbtInfoController::class, 'exambroFullStatus'])
    ->middleware(['throttle:exambro-api', 'exambro.key'])
    ->name('cbt.exambro.full.status');

// Alias for legacy APK that calls /api/exambro-config/download
Route::match(['GET', 'OPTIONS'], '/api/exambro-config/download', [CbtInfoController::class, 'exambroInfo'])
    ->middleware(['throttle:exambro-api', 'exambro.key'])
    ->name('cbt.exambro.config.download');
Route::get('/api/endpoints', function (Request $request) {
    $buildUrl = static function (string $routeName) {
        return route($routeName);
    };

    $endpoints = [
        [
            'name' => 'api_endpoints',
            'method' => 'GET',
            'path' => '/api/endpoints',
            'url' => $buildUrl('cbt.api.endpoints'),
            'requires_key' => false,
        ],
        [
            'name' => 'token_info',
            'method' => 'GET',
            'path' => '/api/token-info',
            'url' => $buildUrl('cbt.token.info'),
            'requires_key' => false,
        ],
        [
            'name' => 'exambro_info',
            'method' => 'GET',
            'path' => '/api/exambro-info',
            'url' => $buildUrl('cbt.exambro.info'),
            'requires_key' => false,
        ],
        [
            'name' => 'server_presence_heartbeat',
            'method' => 'POST',
            'path' => '/api/server-presence/heartbeat',
            'url' => $buildUrl('cbt.server.presence.heartbeat'),
            'requires_key' => false,
        ],
        [
            'name' => 'exambro_status_token',
            'method' => 'GET',
            'path' => '/api/exambro-status/token',
            'url' => $buildUrl('cbt.exambro.status.token'),
            'requires_key' => false,
        ],
        [
            'name' => 'exambro_status_warning',
            'method' => 'GET',
            'path' => '/api/exambro-status/peringatan',
            'url' => $buildUrl('cbt.exambro.status.warning'),
            'requires_key' => false,
        ],
    ];

    $origin = (string) $request->headers->get('Origin', '');
    $allowOrigin = $request->getSchemeAndHttpHost();
    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        if (is_string($originHost) && strcasecmp($originHost, $request->getHost()) === 0) {
            $allowOrigin = $origin;
        }
    }

    return response()->json([
        'message' => 'Daftar API internal untuk Exambro',
        'count' => count($endpoints),
        'base_url' => url('/'),
        'auth' => [
            'type' => 'internal_exambro_access',
            'api_key_required' => false,
        ],
        'endpoints' => $endpoints,
    ])->withHeaders([
        'Access-Control-Allow-Origin' => $allowOrigin,
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin',
    ]);
})->middleware('throttle:public-api')->name('cbt.api.endpoints');

// Config API endpoints (APK configuration with versioning)
Route::match(['GET', 'OPTIONS'], '/api/version.json', [ConfigApiController::class, 'getVersion'])
    ->middleware('throttle:config-api')
    ->name('api.config.version');
Route::match(['GET', 'OPTIONS'], '/api/config.json', [ConfigApiController::class, 'getConfig'])
    ->middleware('throttle:config-api')
    ->name('api.config.get');
Route::match(['GET', 'OPTIONS'], '/api/config/health', [ConfigApiController::class, 'health'])
    ->middleware('throttle:config-api')
    ->name('api.config.health');

// Public direct links (without /api prefix)
Route::match(['GET', 'OPTIONS'], '/version.json', [ConfigApiController::class, 'getVersion'])
    ->middleware('throttle:config-api')
    ->name('public.version');
Route::match(['GET', 'OPTIONS'], '/config.json', [ConfigApiController::class, 'getConfig'])
    ->middleware('throttle:config-api')
    ->name('public.config');

// Admin config update (requires authentication)
Route::post('/api/config/update', [ConfigApiController::class, 'updateConfig'])
    ->middleware('auth:sanctum')
    ->name('api.config.update');

Route::get('/admin/login', [CbtInfoController::class, 'showLogin'])->name('cbt.admin.login');
Route::post('/admin/login', [CbtInfoController::class, 'login'])->name('cbt.admin.login.submit');

Route::get('/admin/cbt-info', [CbtInfoController::class, 'admin'])->name('cbt.admin');
Route::post('/admin/cbt-info', [CbtInfoController::class, 'update'])->name('cbt.update');
Route::post('/admin/exambro-toggle', [CbtInfoController::class, 'toggleExambro'])->name('cbt.exambro.toggle');
Route::post('/admin/exambro-warning-toggle', [CbtInfoController::class, 'toggleExambroWarning'])->name('cbt.exambro.warning.toggle');
Route::post('/admin/exambro-token/generate', [CbtInfoController::class, 'generateExambroToken'])->name('cbt.exambro.token.generate');
Route::post('/admin/exambro-exit-emergency-pin', [CbtInfoController::class, 'updateExambroEmergencyExitPin'])->name('cbt.exambro.exit-emergency-pin.update');
Route::post('/admin/exambro-token-visibility-toggle', [CbtInfoController::class, 'toggleExambroTokenVisibilityForPage'])->name('cbt.exambro.token.visibility.toggle');
Route::post('/admin/exambro-pin-toggle', [CbtInfoController::class, 'toggleExambroPinStatus'])->name('cbt.exambro.pin.toggle');
Route::get('/admin/neo-exam/download-zip', [CbtInfoController::class, 'downloadNeoExamZip'])->name('cbt.neo-exam.zip.download');
Route::post('/admin/user-agent-settings', [CbtInfoController::class, 'updateUserAgentSettings'])->name('cbt.user-agent.update');
Route::post('/admin/server', [CbtInfoController::class, 'addServer'])->name('cbt.server.add');
Route::post('/admin/server/{key}', [CbtInfoController::class, 'updateServerSettings'])->name('cbt.server.update');
Route::post('/admin/server/{key}/visibility-toggle', [CbtInfoController::class, 'toggleServerVisibility'])->name('cbt.server.visibility.toggle');
Route::post('/admin/server/{key}/delete', [CbtInfoController::class, 'deleteServer'])->name('cbt.server.delete');
Route::post('/admin/flush-cache', [CbtInfoController::class, 'flushCache'])->name('cbt.admin.flush-cache');
Route::post('/admin/logout', [CbtInfoController::class, 'logout'])->name('cbt.admin.logout');
