<?php

namespace App\Filament\Resources\Permissions;

use App\Filament\Resources\Permissions\Pages\CreatePermission;
use App\Filament\Resources\Permissions\Pages\EditPermission;
use App\Filament\Resources\Permissions\Pages\ListPermissions;
use App\Filament\Resources\Permissions\Schemas\PermissionForm;
use App\Filament\Resources\Permissions\Tables\PermissionsTable;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Models\Permission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PermissionResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Permission::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $pluralModelLabel = 'Permissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return PermissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermissionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissions::route('/'),
            'create' => CreatePermission::route('/create'),
            'edit' => EditPermission::route('/{record}/edit'),
        ];
    }
}
