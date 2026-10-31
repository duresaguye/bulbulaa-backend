<?php

namespace App\Filament\Resources\MonthlyReports;

use App\Filament\Resources\MonthlyReports\Pages\CreateMonthlyReport;
use App\Filament\Resources\MonthlyReports\Pages\EditMonthlyReport;
use App\Filament\Resources\MonthlyReports\Pages\ListMonthlyReports;
use App\Filament\Resources\MonthlyReports\Pages\ViewMonthlyReport;
use App\Filament\Resources\MonthlyReports\Schemas\MonthlyReportForm;
use App\Filament\Resources\MonthlyReports\Tables\MonthlyReportsTable;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\OfficeScope;
use App\Models\MonthlyReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MonthlyReportResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = MonthlyReport::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $pluralModelLabel = 'Monthly reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Monthly reports';

    protected static ?string $modelLabel = 'monthly report';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return MonthlyReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MonthlyReportsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = OfficeScope::applyVisible(parent::getEloquentQuery()->with(['department', 'preparedBy', 'submittedBy']));
        $user = OfficeScope::user();

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
        return $record->isEditable() && OfficeScope::canWriteFor((int) $record->department_id);
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonthlyReports::route('/'),
            'create' => CreateMonthlyReport::route('/create'),
            'view' => ViewMonthlyReport::route('/{record}'),
            'edit' => EditMonthlyReport::route('/{record}/edit'),
        ];
    }
}
