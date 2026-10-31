<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ReportingCalendar;
use App\Services\TargetProgress;
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
        Gate::before(function (?User $user, string $ability): ?bool {
            if ($user === null) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
