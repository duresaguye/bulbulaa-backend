<?php

namespace App\Filament\Resources\WeeklyProgressReports;

use App\Filament\Resources\WeeklyProgressReports\Pages\CreateWeeklyProgressReport;
use App\Filament\Resources\WeeklyProgressReports\Pages\EditWeeklyProgressReport;
use App\Filament\Resources\WeeklyProgressReports\Pages\ListWeeklyProgressReports;
use App\Filament\Resources\WeeklyProgressReports\Pages\ViewWeeklyProgressReport;
use App\Filament\Resources\WeeklyProgressReports\Schemas\WeeklyProgressReportForm;
use App\Filament\Resources\WeeklyProgressReports\Tables\WeeklyProgressReportsTable;
use App\Filament\Support\OfficeScope;
use App\Models\Department;
use App\Models\User;
use App\Models\WeeklyProgressReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WeeklyProgressReportResource extends Resource
{
    protected static ?string $model = WeeklyProgressReport::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $pluralModelLabel = 'Weekly reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Weekly reports';

    protected static ?string $modelLabel = 'weekly report';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return WeeklyProgressReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WeeklyProgressReportsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = OfficeScope::applyVisible(parent::getEloquentQuery()->with(['department', 'preparedBy', 'submittedBy']));
        $user = OfficeScope::user();

        /** Drafts stay inside the office that is writing them. */
        if ($user !== null && ! $user->hasOrganizationWideScope()) {
            $query->where(fn (Builder $query) => $query
                ->where('status', '!=', 'draft')
                ->orWhereIn('department_id', $user->memberDepartmentIds()));
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('reports.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('reports.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('reports.manage')
            && $record->isEditable()
            && static::canManageDepartment($record->department);
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }

    public static function canManageDepartment(Department $department): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->hasPermission('reports.targets.manage')
            || $user->departments()->whereKey($department->id)->exists());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWeeklyProgressReports::route('/'),
            'create' => CreateWeeklyProgressReport::route('/create'),
            'view' => ViewWeeklyProgressReport::route('/{record}'),
            'edit' => EditWeeklyProgressReport::route('/{record}/edit'),
        ];
    }
}
