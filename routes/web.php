<?php

use App\Http\Controllers\CbtInfoController;
use App\Http\Controllers\ConfigApiController;
use App\Http\Controllers\GitHubWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\DownloadController;

$loadBalancerPath = '/go-cbt';

try {
    if (Schema::hasTable('web_settings')) {
        $row = DB::table('web_settings')
            ->where('setting_key', 'cbt_load_balancer_path')
            ->first(['setting_value']);

        if ($row && isset($row->setting_value)) {
            $path = json_decode((string) $row->setting_value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $path = is_string($path) ? $path : '';
            } else {
                $path = (string) $row->setting_value;
            }

            $path = trim($path);
            if ($path !== '') {
                $path = '/' . ltrim($path, '/');
                if ($path !== '') {
                    $loadBalancerPath = $path;
                }
            }
        }
    }
} catch (\Throwable $e) {
    // Jatuhkan ke default path /go-cbt jika setting belum tersedia.
}

Route::get($loadBalancerPath, [CbtInfoController::class, 'loadBalancer'])->name('cbt.lb');

Route::get('/', [CbtInfoController::class, 'index'])->name('cbt.index');
Route::get('/info', [CbtInfoController::class, 'index']);
Route::get('/exambro', [CbtInfoController::class, 'exambroPage'])
    ->middleware('exambro.key')
    ->name('cbt.exambro.page');
Route::get('/exambro/connect/{serverKey}', [CbtInfoController::class, 'connectServer'])
    ->middleware('exambro.key')
    ->name('cbt.exambro.connect');

Route::post('/webhook/github', [GitHubWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhook.github');

// Public read-only endpoints needed by Exambro page.
Route::match(['GET', 'OPTIONS'], '/api/exambro-info', [CbtInfoController::class, 'exambroInfo'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('cbt.exambro.info');
Route::match(['GET', 'OPTIONS'], '/api/mirror_list.json', [CbtInfoController::class, 'mirrorList'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('cbt.mirror-list');
// Explicitly disable legacy token-info endpoint with 403 to avoid accidental exposure
Route::match(['GET', 'OPTIONS'], '/api/token-info', function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API token-info disabled.',
    ], 403);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::match(['GET', 'OPTIONS'], '/api/exambro-token-status', [CbtInfoController::class, 'exambroTokenStatus'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('cbt.exambro.token.status');
Route::match(['POST', 'OPTIONS'], '/api/server-presence/heartbeat', [CbtInfoController::class, 'heartbeatServerPresence'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('cbt.server.presence.heartbeat');
// Internal version sync endpoint removed

// Public config endpoints for Exambro client sync flow:
// 1) always fetch version.json (no-cache)
// 2) fetch config.json only when version changes
Route::match(['GET', 'OPTIONS'], '/api/version.json', [ConfigApiController::class, 'getVersion'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('config.version');
Route::match(['GET', 'OPTIONS'], '/assets/app/version.json', [ConfigApiController::class, 'getVersion'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::match(['GET', 'OPTIONS'], '/api/config.json', [ConfigApiController::class, 'getConfig'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('config.file');
Route::match(['GET', 'OPTIONS'], '/assets/app/config.json', [ConfigApiController::class, 'getConfig'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::match(['GET', 'OPTIONS'], '/api/config/health', [ConfigApiController::class, 'health'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('config.health');

// Disable all web API endpoints so external apps cannot submit requests.
Route::any('/api/{any}', function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint disabled.',
    ], 403);
})->where('any', '.*');

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
// Version sync admin routes removed
Route::post('/admin/server', [CbtInfoController::class, 'addServer'])->name('cbt.server.add');
Route::post('/admin/server/{key}', [CbtInfoController::class, 'updateServerSettings'])->name('cbt.server.update');
Route::post('/admin/server/{key}/visibility-toggle', [CbtInfoController::class, 'toggleServerVisibility'])->name('cbt.server.visibility.toggle');
Route::post('/admin/server/{key}/lb-toggle', [CbtInfoController::class, 'toggleServerLoadBalancing'])->name('cbt.server.lb.toggle');
Route::post('/admin/server/{key}/selection-toggle', [CbtInfoController::class, 'toggleServerSelection'])->name('cbt.server.selection.toggle');
Route::post('/admin/server/{key}/selection-timer', [CbtInfoController::class, 'setServerSelectionTimer'])->name('cbt.server.selection.timer');
Route::post('/admin/server/selection-all/timer', [CbtInfoController::class, 'setAllServerSelectionTimer'])->name('cbt.server.selection.all.timer');
Route::post('/admin/server/{key}/delete', [CbtInfoController::class, 'deleteServer'])->name('cbt.server.delete');
Route::post('/admin/pin-exambro', [CbtInfoController::class, 'updatePinExambro'])->name('cbt.admin.pin.exambro');
Route::post('/admin/exambro-fetch', [CbtInfoController::class, 'fetchExambroTokenFromLb'])->name('cbt.exambro.fetch');
Route::post('/admin/exambro-token/update', [CbtInfoController::class, 'updateExambroTokenFromAdmin'])->name('cbt.exambro.token.update');
Route::post('/admin/logout', [CbtInfoController::class, 'logout'])->name('cbt.admin.logout');
// Admin-only debug: show raw CBT token DB row (useful to compare values)
Route::get('/admin/debug/cbt-token', [CbtInfoController::class, 'debugCbtToken'])->name('cbt.debug.cbt-token');
// Stream a zip of the current repository HEAD (disabled unless ENABLE_SOURCE_DOWNLOAD=true)
Route::get('/download/source', [DownloadController::class, 'downloadLatest']);
