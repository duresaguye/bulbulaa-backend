<?php

namespace App\Filament\Resources\MonthlyPlans;

use App\Filament\Resources\MonthlyPlans\Pages\CreateMonthlyPlan;
use App\Filament\Resources\MonthlyPlans\Pages\EditMonthlyPlan;
use App\Filament\Resources\MonthlyPlans\Pages\ListMonthlyPlans;
use App\Filament\Resources\MonthlyPlans\Schemas\MonthlyPlanForm;
use App\Filament\Resources\MonthlyPlans\Tables\MonthlyPlansTable;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Models\AnnualTarget;
use App\Models\MonthlyPlan;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MonthlyPlanResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = MonthlyPlan::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $pluralModelLabel = 'Monthly targets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Monthly targets';

    protected static ?string $modelLabel = 'monthly target';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return MonthlyPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MonthlyPlansTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user instanceof User || $user->hasPermission('reports.targets.manage')) {
            return $query;
        }

        return $query->whereHas('annualTarget', fn (Builder $targetQuery) => $targetQuery
            ->whereIn('department_id', $user->visibleDepartmentIds()));
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
        return auth()->user() instanceof User
            && auth()->user()->hasPermission('reports.manage')
            && static::belongsToCurrentUsersDepartment($record);
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }

    public static function belongsToCurrentUsersDepartment(MonthlyPlan $monthlyPlan): bool
    {
        return static::canManageAnnualTarget($monthlyPlan->annualTarget);
    }

    public static function canManageAnnualTarget(AnnualTarget $annualTarget): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->departments()
            ->whereKey($annualTarget->department_id)
            ->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonthlyPlans::route('/'),
            'create' => CreateMonthlyPlan::route('/create'),
            'edit' => EditMonthlyPlan::route('/{record}/edit'),
        ];
    }
}
