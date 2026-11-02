<?php

use App\Http\Controllers\Api\PublicContentController;
use App\Http\Controllers\Api\PublicPostController;
use App\Http\Controllers\Api\PublicReportController;
use Illuminate\Support\Facades\Route;

/** Public, read-only website feed. Only published posts; no sign-in needed. */
Route::prefix('public')->middleware('throttle:120,1')->group(function (): void {
    Route::get('posts', [PublicPostController::class, 'index'])->name('api.public.posts.index');
    Route::get('posts/{slug}', [PublicPostController::class, 'show'])->where('slug', '[A-Za-z0-9\-_]+')->name('api.public.posts.show');
    Route::get('projects', [PublicContentController::class, 'projects'])->name('api.public.projects.index');
    Route::get('projects/{slug}', [PublicContentController::class, 'project'])->where('slug', '[A-Za-z0-9\-_]+')->name('api.public.projects.show');
    Route::get('services', [PublicContentController::class, 'services'])->name('api.public.services.index');
    Route::get('tourism-places', [PublicContentController::class, 'tourismPlaces'])->name('api.public.tourism-places.index');
    Route::get('documents', [PublicContentController::class, 'documents'])->name('api.public.documents.index');
    Route::get('alerts', [PublicContentController::class, 'alerts'])->name('api.public.alerts.index');
    Route::get('settings', [PublicContentController::class, 'settings'])->name('api.public.settings');
    Route::get('investment-sectors', [PublicContentController::class, 'investmentSectors'])->name('api.public.investment-sectors.index');
    Route::get('about-sections', [PublicContentController::class, 'aboutSections'])->name('api.public.about-sections.index');
    Route::get('offices', [PublicContentController::class, 'offices'])->name('api.public.offices.index');
});

/** Messages and reports from the public. Strict limits: a few per minute and per day from one connection. */
Route::post('public/reports', [PublicReportController::class, 'store'])
    ->middleware(['throttle:public-reports'])
    ->name('api.public.reports.store');
