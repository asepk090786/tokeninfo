<?php

use App\Http\Controllers\CbtInfoController;
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
    ->name('cbt.exambro.api-key.download.app');

Route::get('/admin/login', [CbtInfoController::class, 'showLogin'])->name('cbt.admin.login');
Route::post('/admin/login', [CbtInfoController::class, 'login'])->name('cbt.admin.login.submit');

Route::get('/admin/cbt-info', [CbtInfoController::class, 'admin'])->name('cbt.admin');
Route::post('/admin/cbt-info', [CbtInfoController::class, 'update'])->name('cbt.update');
Route::post('/admin/exambro-toggle', [CbtInfoController::class, 'toggleExambro'])->name('cbt.exambro.toggle');
Route::post('/admin/exambro-warning-toggle', [CbtInfoController::class, 'toggleExambroWarning'])->name('cbt.exambro.warning.toggle');
Route::post('/admin/exambro-token/generate', [CbtInfoController::class, 'generateExambroToken'])->name('cbt.exambro.token.generate');
Route::post('/admin/exambro-token-visibility-toggle', [CbtInfoController::class, 'toggleExambroTokenVisibilityForPage'])->name('cbt.exambro.token.visibility.toggle');
Route::post('/admin/exambro-api-key/generate', [CbtInfoController::class, 'generateExambroApiKey'])->name('cbt.exambro.api-key.generate');
Route::get('/admin/exambro-api-key/download', [CbtInfoController::class, 'downloadExambroApiConfig'])->name('cbt.exambro.api-key.download');
Route::post('/admin/logout', [CbtInfoController::class, 'logout'])->name('cbt.admin.logout');
