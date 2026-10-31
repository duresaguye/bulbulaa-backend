<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ReportingCalendar;
use App\Services\TargetProgress;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TargetProgress::class);
        $this->app->scoped(ReportingCalendar::class);
    }

    public function boot(): void
    {
        /** Carbon's Afaan Oromoo has no words for "ago" and "from now", so relative times came out half in English. */
        Carbon::getTranslator()->setMessages('om', [
            'ago' => ':time dura',
            'from_now' => ':time booda',
            'before' => ':time dura',
            'after' => ':time booda',
        ]);

        Gate::before(function (?User $user, string $ability): ?bool {
            if ($user === null) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
