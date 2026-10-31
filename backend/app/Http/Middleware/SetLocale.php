<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locales;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the signed-in person's chosen language, then the one picked on the sign-in page, then the default.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = collect([
            $user instanceof User ? $user->locale : null,
            $request->session()->get('locale'),
        ])->first(fn (?string $candidate): bool => Locales::isSupported($candidate)) ?? Locales::default();

        App::setLocale($locale);
        Carbon::setLocale($locale);
        CarbonImmutable::setLocale($locale);

        return $next($request);
    }
}
