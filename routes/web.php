<?php

use App\Http\Controllers\CbtInfoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CbtInfoController::class, 'index'])->name('cbt.index');
Route::get('/api/token-info', [CbtInfoController::class, 'tokenInfo'])->name('cbt.token.info');

Route::get('/admin/login', [CbtInfoController::class, 'showLogin'])->name('cbt.admin.login');
Route::post('/admin/login', [CbtInfoController::class, 'login'])->name('cbt.admin.login.submit');

Route::get('/admin/cbt-info', [CbtInfoController::class, 'admin'])->name('cbt.admin');
Route::post('/admin/cbt-info', [CbtInfoController::class, 'update'])->name('cbt.update');
Route::post('/admin/exambro-toggle', [CbtInfoController::class, 'toggleExambro'])->name('cbt.exambro.toggle');
Route::post('/admin/logout', [CbtInfoController::class, 'logout'])->name('cbt.admin.logout');
