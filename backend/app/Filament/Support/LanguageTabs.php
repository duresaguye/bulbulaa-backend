<?php

namespace App\Filament\Support;

use App\Support\Locales;
use Closure;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * One tab per website language, Afaan Oromoo first and required. The fields callback receives the language code
 * and whether that language is required, and returns the fields for that tab.
 */
class LanguageTabs
{
    /**
     * @param  Closure(string $locale, bool $required): array<int, mixed>  $fields
     */
    public static function make(Closure $fields): Tabs
    {
        return Tabs::make(__('Language'))
            ->tabs(collect(array_keys(Locales::NAMES))->map(function (string $locale) use ($fields): Tab {
                $required = $locale === 'om';

                return Tab::make(Locales::NAMES[$locale].($required ? ' *' : ''))
                    ->schema($fields($locale, $required))
                    ->extraAttributes(['lang' => $locale]);
            })->all());
    }
}
