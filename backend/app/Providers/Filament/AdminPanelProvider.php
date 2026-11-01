<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Controllers\CitizenReportPhotoController;
use App\Http\Controllers\ReportExportController;
use App\Http\Middleware\SetLocale;
use App\Support\Locales;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): string => auth()->check() ? '' : view('filament.components.login-photo')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => view('filament.components.login-introduction')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.components.language-links')->render(),
            )
            ->userMenuItems(self::languageMenuItems())
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => view('filament.components.reporting-period')->render(),
            )
            ->brandName(config('app.town_name').' Administration')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('2.25rem')
            ->colors([
                /** Bulbulaa forest green (shared with the public website), brand colour at shade 600 where Filament draws buttons and links. */
                'primary' => [
                    50 => '#eef5f0',
                    100 => '#dbeadf',
                    200 => '#b9d6c1',
                    300 => '#8fbb9c',
                    400 => '#649b74',
                    500 => '#437d55',
                    600 => '#2c6340',
                    700 => '#214f33',
                    800 => '#183b23',
                    900 => '#112c1a',
                    950 => '#0a1d10',
                ],
                'gray' => Color::Zinc,
                'info' => Color::Blue,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
            ])
            ->font('IBM Plex Sans')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->maxContentWidth('7xl')
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('Planning')),
                NavigationGroup::make(fn (): string => __('Reports')),
                NavigationGroup::make(fn (): string => __('Oversight')),
                NavigationGroup::make(fn (): string => __('Website')),
                NavigationGroup::make(fn (): string => __('Organization')),
                NavigationGroup::make(fn (): string => __('Administration'))->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([SetLocale::class], isPersistent: true)
            ->authenticatedRoutes(function (): void {
                Route::get('citizen-reports/{report}/photos/{index}', [CitizenReportPhotoController::class, 'show'])->whereNumber(['report', 'index'])->name('citizen-reports.photo');
                Route::get('exports/office-performance/print', [ReportExportController::class, 'printOfficePerformance'])->name('exports.office-performance.print');
                Route::get('exports/office-performance/excel', [ReportExportController::class, 'excelOfficePerformance'])->name('exports.office-performance.excel');
                Route::get('exports/{type}/{record}/print', [ReportExportController::class, 'printReport'])->whereIn('type', ['weekly', 'monthly', 'annual'])->whereNumber('record')->name('exports.report.print');
                Route::get('exports/{type}/{record}/excel', [ReportExportController::class, 'excelReport'])->whereIn('type', ['weekly', 'monthly', 'annual'])->whereNumber('record')->name('exports.report.excel');
            })
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * One user-menu entry per language, each named in its own language, with a tick on the one in use.
     *
     * @return list<Action>
     */
    private static function languageMenuItems(): array
    {
        return collect(Locales::NAMES)
            ->map(fn (string $name, string $code): Action => Action::make('language_'.$code)
                ->label($name)
                ->icon(fn (): string => app()->getLocale() === $code ? 'heroicon-m-check' : 'heroicon-o-language')
                ->url(fn (): string => route('admin.language', $code)))
            ->values()
            ->all();
    }
}
