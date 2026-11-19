<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustinHoltWeb\Dipstick\Http\Controllers\ReportController;
use JustinHoltWeb\Dipstick\Http\Middleware\RequireAdmin;

// The cp group already applies auth + can:accessCp (and CSRF via `web`). Admins only.
Route::middleware(RequireAdmin::class)->prefix('dipstick')->group(function () {
    Route::get('', [ReportController::class, 'index']);
    Route::get('audits/{scanId}', [ReportController::class, 'show'])->whereNumber('scanId');
    Route::post('run', [ReportController::class, 'run']);
});
