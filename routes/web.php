<?php

declare(strict_types=1);

use Cybernerdie\Glint\Http\Controllers\AlertsController;
use Cybernerdie\Glint\Http\Controllers\AnalyticsController;
use Cybernerdie\Glint\Http\Controllers\AssetController;
use Cybernerdie\Glint\Http\Controllers\CostsController;
use Cybernerdie\Glint\Http\Controllers\DashboardController;
use Cybernerdie\Glint\Http\Controllers\GenerationsController;
use Cybernerdie\Glint\Http\Controllers\TracesController;
use Cybernerdie\Glint\Http\Controllers\UsersController;
use Cybernerdie\Glint\Middleware\GlintSecurityHeaders;
use Illuminate\Support\Facades\Route;

$glintPath = is_string($path = config('glint.path', 'glint')) && $path !== '' ? $path : 'glint';

$middleware = array_merge(
    (array) config('glint.middleware', ['web']),
    [GlintSecurityHeaders::class, 'throttle:120,1']
);

Route::prefix($glintPath)
    ->middleware($middleware)
    ->name('glint.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/traces', [TracesController::class, 'index'])->name('traces.index');
        Route::get('/traces/{traceId}', [TracesController::class, 'show'])->name('traces.show');
        Route::get('/generations', [GenerationsController::class, 'index'])->name('generations.index');
        Route::get('/generations/{generationId}', [GenerationsController::class, 'show'])->name('generations.show');
        Route::get('/costs', [CostsController::class, 'index'])->name('costs.index');
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::get('/users/{userId}', [UsersController::class, 'show'])->name('users.show')->where('userId', '.{1,255}');
        Route::get('/analytics/latency', AnalyticsController::class)->name('analytics.latency');
        Route::get('/alerts', [AlertsController::class, 'index'])->name('alerts.index');
        Route::get('/alerts/create', [AlertsController::class, 'create'])->name('alerts.create');
        Route::post('/alerts', [AlertsController::class, 'store'])->name('alerts.store');
        Route::get('/alerts/{alertRule}/edit', [AlertsController::class, 'edit'])->name('alerts.edit');
        Route::put('/alerts/{alertRule}', [AlertsController::class, 'update'])->name('alerts.update');
        Route::post('/alerts/{alertRule}/toggle', [AlertsController::class, 'toggle'])->name('alerts.toggle');
        Route::delete('/alerts/{alertRule}', [AlertsController::class, 'destroy'])->name('alerts.destroy');
    });

Route::prefix($glintPath)
    ->name('glint.')
    ->middleware('throttle:120,1')
    ->get('/apple-touch-icon.png', [AssetController::class, 'touchIcon'])
    ->name('touch-icon');
