<?php

use App\Http\Controllers\CbtInfoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [CbtInfoController::class, 'index'])->name('cbt.index');
Route::get('/exambro', [CbtInfoController::class, 'exambroPage'])
    ->middleware('exambro.key')
    ->name('cbt.exambro.page');
Route::get('/api/token-info', [CbtInfoController::class, 'tokenInfo'])->name('cbt.token.info');
Route::match(['GET', 'OPTIONS'], '/api/exambro-info', [CbtInfoController::class, 'exambroInfo'])
    ->middleware('exambro.key')
    ->name('cbt.exambro.info');
Route::match(['GET', 'OPTIONS'], '/api/exambro-status/token', [CbtInfoController::class, 'exambroTokenStatus'])
    ->middleware('exambro.key')
    ->name('cbt.exambro.status.token');
Route::match(['GET', 'OPTIONS'], '/api/exambro-status/peringatan', [CbtInfoController::class, 'exambroWarningStatus'])
    ->middleware('exambro.key')
    ->name('cbt.exambro.status.warning');
Route::get('/api/exambro-config/download', [CbtInfoController::class, 'downloadExambroApiConfigForApp'])
    ->middleware('exambro.key')
    ->name('cbt.exambro.api-key.download.app');
Route::get('/api/endpoints', function (Request $request) {
    $providedKey = trim((string) $request->query('key', ''));

    $buildUrl = function (string $routeName) use ($providedKey) {
        return $providedKey !== ''
            ? route($routeName, ['key' => $providedKey])
            : route($routeName);
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
            'requires_key' => true,
        ],
        [
            'name' => 'exambro_status_token',
            'method' => 'GET',
            'path' => '/api/exambro-status/token',
            'url' => $buildUrl('cbt.exambro.status.token'),
            'requires_key' => true,
        ],
        [
            'name' => 'exambro_status_warning',
            'method' => 'GET',
            'path' => '/api/exambro-status/peringatan',
            'url' => $buildUrl('cbt.exambro.status.warning'),
            'requires_key' => true,
        ],
        [
            'name' => 'exambro_config_download',
            'method' => 'GET',
            'path' => '/api/exambro-config/download',
            'url' => $buildUrl('cbt.exambro.api-key.download.app'),
            'requires_key' => false,
        ],
    ];

    return response()->json([
        'message' => 'Daftar API internal untuk Exambro',
        'count' => count($endpoints),
        'base_url' => url('/'),
        'auth' => [
            'header' => 'X-Exambro-Key',
            'query_param' => 'key',
            'provided_key_included_in_url' => $providedKey !== '',
        ],
        'endpoints' => $endpoints,
    ])->withHeaders([
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept, Origin, X-Exambro-Key',
    ]);
})->name('cbt.api.endpoints');

Route::get('/admin/login', [CbtInfoController::class, 'showLogin'])->name('cbt.admin.login');
Route::post('/admin/login', [CbtInfoController::class, 'login'])->name('cbt.admin.login.submit');

Route::get('/admin/cbt-info', [CbtInfoController::class, 'admin'])->name('cbt.admin');
Route::post('/admin/cbt-info', [CbtInfoController::class, 'update'])->name('cbt.update');
Route::post('/admin/exambro-toggle', [CbtInfoController::class, 'toggleExambro'])->name('cbt.exambro.toggle');
Route::post('/admin/exambro-warning-toggle', [CbtInfoController::class, 'toggleExambroWarning'])->name('cbt.exambro.warning.toggle');
Route::post('/admin/exambro-token/generate', [CbtInfoController::class, 'generateExambroToken'])->name('cbt.exambro.token.generate');
Route::post('/admin/exambro-token-visibility-toggle', [CbtInfoController::class, 'toggleExambroTokenVisibilityForPage'])->name('cbt.exambro.token.visibility.toggle');
Route::post('/admin/exambro-pin-toggle', [CbtInfoController::class, 'toggleExambroPinStatus'])->name('cbt.exambro.pin.toggle');
Route::post('/admin/exambro-api-key/generate', [CbtInfoController::class, 'generateExambroApiKey'])->name('cbt.exambro.api-key.generate');
Route::get('/admin/exambro-api-key/download', [CbtInfoController::class, 'downloadExambroApiConfig'])->name('cbt.exambro.api-key.download');
Route::post('/admin/logout', [CbtInfoController::class, 'logout'])->name('cbt.admin.logout');
