<?php

use App\Http\Controllers\Api\PublicPostController;
use Illuminate\Support\Facades\Route;

/** Public, read-only website feed. Only published posts; no sign-in needed. */
Route::prefix('public')->middleware('throttle:120,1')->group(function (): void {
    Route::get('posts', [PublicPostController::class, 'index'])->name('api.public.posts.index');
    Route::get('posts/{slug}', [PublicPostController::class, 'show'])->where('slug', '[A-Za-z0-9\-_]+')->name('api.public.posts.show');
});
