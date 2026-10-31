<?php

use App\Models\User;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/** Switches the admin language. Signed-in staff keep the choice on their account; the sign-in page keeps it for the visit. */
Route::get('/admin/language/{locale}', function (Request $request, string $locale) {
    abort_unless(Locales::isSupported($locale), 404);

    $request->session()->put('locale', $locale);

    if (($user = $request->user()) instanceof User) {
        $user->forceFill(['locale' => $locale])->save();
    }

    return redirect()->back(fallback: '/admin');
})->name('admin.language');
