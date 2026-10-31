<?php

namespace App\Filament\Support\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Passes a resource's English labels through the translator, so lang/om.json and lang/am.json can rename them.
 */
trait TranslatesResourceLabels
{
    public static function getModelLabel(): string
    {
        return __(parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }

    public static function getNavigationLabel(): string|Htmlable
    {
        $label = parent::getNavigationLabel();

        return is_string($label) ? __($label) : $label;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = parent::getNavigationGroup();

        return is_string($group) ? __($group) : $group;
    }
}
