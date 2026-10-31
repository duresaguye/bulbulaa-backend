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

    /** Return to the page the person was on, but never to a download (that would start the download again). */
    $previous = (string) $request->headers->get('referer');
    $sameSite = str_starts_with($previous, $request->getSchemeAndHttpHost().'/');
    $isDownload = str_contains(parse_url($previous, PHP_URL_PATH) ?? '', '/admin/exports/');

    return redirect($sameSite && ! $isDownload ? $previous : '/admin');
})->name('admin.language');
