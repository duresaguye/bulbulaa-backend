<?php

namespace App\Filament\Resources\AnnualTargets;

use App\Filament\Resources\AnnualTargets\Pages\AllocateAnnualTarget;
use App\Filament\Resources\AnnualTargets\Pages\CreateAnnualTarget;
use App\Filament\Resources\AnnualTargets\Pages\EditAnnualTarget;
use App\Filament\Resources\AnnualTargets\Pages\ListAnnualTargets;
use App\Filament\Resources\AnnualTargets\Pages\ViewAnnualTarget;
use App\Filament\Resources\AnnualTargets\Schemas\AnnualTargetForm;
use App\Filament\Resources\AnnualTargets\Tables\AnnualTargetsTable;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\OfficeScope;
use App\Models\AnnualTarget;
use App\Services\TargetAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnnualTargetResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = AnnualTarget::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $pluralModelLabel = 'Annual targets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Annual targets';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AnnualTargetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnnualTargetsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return OfficeScope::applyVisible(parent::getEloquentQuery());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('reports.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('reports.targets.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = OfficeScope::user();

        return $user !== null && app(TargetAssignment::class)->canEdit($user, $record);
    }

    /**
     * Targets with reported progress or distributed shares are deactivated rather than deleted, to keep the record.
     */
    public static function canDelete($record): bool
    {
        return static::canEdit($record)
            && ! $record->children()->exists()
            && ! $record->hasReportedProgress();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnualTargets::route('/'),
            'create' => CreateAnnualTarget::route('/create'),
            'view' => ViewAnnualTarget::route('/{record}'),
            'edit' => EditAnnualTarget::route('/{record}/edit'),
            'allocate' => AllocateAnnualTarget::route('/{record}/allocate'),
        ];
    }
}
