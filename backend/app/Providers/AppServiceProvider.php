<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ReportingCalendar;
use App\Services\TargetProgress;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        /** The upload box's own text is English-only, and browser date inputs follow the computer's region. */
        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->placeholder(fn (): string => __('Drag a file here or click to choose one')));
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->native(false)->displayFormat('d/m/Y'));
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->native(false)->displayFormat('d/m/Y H:i'));

        RateLimiter::for('public-reports', fn (Request $request): array => [
            Limit::perMinute(3)->by('report-minute:'.$request->ip()),
            Limit::perDay(20)->by('report-day:'.$request->ip()),
        ]);

        Gate::before(function (?User $user, string $ability): ?bool {
            if ($user === null) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
