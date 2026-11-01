<?php

namespace App\Filament\Support;

use App\Filament\Support\Concerns\TranslatesResourceLabels;
use Filament\Resources\Resource;

/**
 * Base for the website sections in the admin. Only people with the content-manager permission may use them.
 */
abstract class WebsiteContentResource extends Resource
{
    use TranslatesResourceLabels;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('cms.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function canReorder(): bool
    {
        return static::canViewAny();
    }
}
