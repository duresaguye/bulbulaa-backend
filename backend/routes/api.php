<?php

use App\Http\Controllers\Api\PublicPostController;
use App\Http\Controllers\Api\PublicReportController;
use Illuminate\Support\Facades\Route;

/** Public, read-only website feed. Only published posts; no sign-in needed. */
Route::prefix('public')->middleware('throttle:120,1')->group(function (): void {
    Route::get('posts', [PublicPostController::class, 'index'])->name('api.public.posts.index');
    Route::get('posts/{slug}', [PublicPostController::class, 'show'])->where('slug', '[A-Za-z0-9\-_]+')->name('api.public.posts.show');
});

/** Messages and reports from the public. Strict limits: a few per minute and per day from one connection. */
Route::post('public/reports', [PublicReportController::class, 'store'])
    ->middleware(['throttle:public-reports'])
    ->name('api.public.reports.store');
