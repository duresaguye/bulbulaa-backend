<?php

namespace App\Filament\Support\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Passes a custom page's English title, navigation label and group through the translator.
 */
trait TranslatesPageLabels
{
    public static function getNavigationLabel(): string|Htmlable
    {
        $label = parent::getNavigationLabel();

        return is_string($label) ? __($label) : $label;
    }

    public function getTitle(): string|Htmlable
    {
        $title = parent::getTitle();

        return is_string($title) ? __($title) : $title;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = parent::getNavigationGroup();

        return is_string($group) ? __($group) : $group;
    }
}
