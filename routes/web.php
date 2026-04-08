<?php

use App\Http\Controllers\CbtInfoController;
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

// Public read-only endpoints needed by Exambro page.
Route::match(['GET', 'OPTIONS'], '/api/exambro-info', [CbtInfoController::class, 'exambroInfo'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('cbt.exambro.info');
Route::match(['GET', 'OPTIONS'], '/api/token-info', [CbtInfoController::class, 'tokenInfo'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('cbt.token.info');
Route::match(['GET', 'OPTIONS'], '/api/mirror_list.json', [CbtInfoController::class, 'mirrorList'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('cbt.mirror-list');
Route::match(['GET', 'OPTIONS'], '/api/exambro-token-status', [CbtInfoController::class, 'exambroTokenStatus'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('cbt.exambro.token.status');
Route::match(['POST', 'OPTIONS'], '/api/server-presence/heartbeat', [CbtInfoController::class, 'heartbeatServerPresence'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('cbt.server.presence.heartbeat');

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
Route::post('/admin/server', [CbtInfoController::class, 'addServer'])->name('cbt.server.add');
Route::post('/admin/server/{key}', [CbtInfoController::class, 'updateServerSettings'])->name('cbt.server.update');
Route::post('/admin/server/{key}/visibility-toggle', [CbtInfoController::class, 'toggleServerVisibility'])->name('cbt.server.visibility.toggle');
Route::post('/admin/server/{key}/lb-toggle', [CbtInfoController::class, 'toggleServerLoadBalancing'])->name('cbt.server.lb.toggle');
Route::post('/admin/server/{key}/selection-toggle', [CbtInfoController::class, 'toggleServerSelection'])->name('cbt.server.selection.toggle');
Route::post('/admin/server/{key}/selection-timer', [CbtInfoController::class, 'setServerSelectionTimer'])->name('cbt.server.selection.timer');
Route::post('/admin/server/selection-all/timer', [CbtInfoController::class, 'setAllServerSelectionTimer'])->name('cbt.server.selection.all.timer');
Route::post('/admin/server/{key}/delete', [CbtInfoController::class, 'deleteServer'])->name('cbt.server.delete');
Route::post('/admin/flush-cache', [CbtInfoController::class, 'flushCache'])->name('cbt.admin.flush-cache');
Route::post('/admin/logout', [CbtInfoController::class, 'logout'])->name('cbt.admin.logout');
