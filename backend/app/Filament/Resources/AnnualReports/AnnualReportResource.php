<?php

namespace App\Filament\Resources\AnnualReports;

use App\Filament\Resources\AnnualReports\Pages\CreateAnnualReport;
use App\Filament\Resources\AnnualReports\Pages\EditAnnualReport;
use App\Filament\Resources\AnnualReports\Pages\ListAnnualReports;
use App\Filament\Resources\AnnualReports\Pages\ViewAnnualReport;
use App\Filament\Resources\AnnualReports\Schemas\AnnualReportForm;
use App\Filament\Support\OfficeScope;
use App\Filament\Support\ReportTableParts;
use App\Models\AnnualReport;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnnualReportResource extends Resource
{
    protected static ?string $model = AnnualReport::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $pluralModelLabel = 'Annual reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Annual reports';

    protected static ?string $modelLabel = 'annual report';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return AnnualReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.name')->label('Office')->searchable()->sortable(),
                TextColumn::make('ethiopian_fiscal_year')->label('Year (E.C.)')->sortable(),
                ReportTableParts::statusColumn(),
                TextColumn::make('preparedBy.name')->label('Prepared by')->toggleable(),
                TextColumn::make('submitted_at')->label('Submitted')->since()->dateTimeTooltip()->placeholder('Not submitted')->sortable(),
            ])
            ->defaultSort('ethiopian_fiscal_year', 'desc')
            ->filters([
                ReportTableParts::statusFilter(),
                ReportTableParts::officeFilter(),
                ReportTableParts::yearFilter('annual_reports'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->emptyStateHeading('No annual reports yet')
            ->emptyStateDescription('At the end of the year each office reports its results against its annual targets.');
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
            'index' => ListAnnualReports::route('/'),
            'create' => CreateAnnualReport::route('/create'),
            'view' => ViewAnnualReport::route('/{record}'),
            'edit' => EditAnnualReport::route('/{record}/edit'),
        ];
    }
}
