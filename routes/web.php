<?php

declare(strict_types=1);

use Cybernerdie\Glint\Http\Controllers\AnalyticsController;
use Cybernerdie\Glint\Http\Controllers\ApiController;
use Cybernerdie\Glint\Http\Controllers\AssetController;
use Cybernerdie\Glint\Http\Controllers\CostsController;
use Cybernerdie\Glint\Http\Controllers\DashboardController;
use Cybernerdie\Glint\Http\Controllers\GenerationsController;
use Cybernerdie\Glint\Http\Controllers\TracesController;
use Cybernerdie\Glint\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

$glintPath = config('glint.path', 'glint');

// Sanitize the path: strip everything except alphanumeric, hyphens, underscores, and forward slashes.
if (! is_string($glintPath) || ! preg_match('/^[a-zA-Z0-9\-_\/]+$/', $glintPath)) {
    $glintPath = 'glint';
}

Route::prefix($glintPath)
    ->middleware(config('glint.middleware', ['web']))
    ->name('glint.')
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/traces', [TracesController::class, 'index'])->name('traces.index');
        Route::get('/traces/{traceId}', [TracesController::class, 'show'])->name('traces.show');
        Route::get('/generations', [GenerationsController::class, 'index'])->name('generations.index');
        Route::get('/generations/{generationId}', [GenerationsController::class, 'show'])->name('generations.show');
        Route::get('/costs', [CostsController::class, 'index'])->name('costs.index');
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::get('/users/{userId}', [UsersController::class, 'show'])->name('users.show');
        Route::get('/analytics/latency', [AnalyticsController::class, 'latency'])->name('analytics.latency');
        Route::get('/api/metrics', [ApiController::class, 'metrics'])->name('api.metrics')->middleware('throttle:60,1');
    });

// The touch icon sits outside the auth middleware: it is a public logo and
// iOS may request it without the session cookies the dashboard requires.
Route::prefix($glintPath)
    ->name('glint.')
    ->middleware('throttle:120,1')
    ->get('/apple-touch-icon.png', [AssetController::class, 'touchIcon'])
    ->name('touch-icon');
