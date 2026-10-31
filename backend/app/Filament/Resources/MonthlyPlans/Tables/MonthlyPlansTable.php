<?php

namespace App\Filament\Resources\MonthlyPlans\Tables;

use App\Filament\Resources\AnnualTargets\AnnualTargetResource;
use App\Filament\Support\OfficeScope;
use App\Models\AnnualTarget;
use App\Models\MonthlyPlan;
use App\Services\EthiopianCalendar;
use App\Services\ReportingCalendar;
use App\Services\TargetProgress;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class MonthlyPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('annualTarget.title')
                    ->label(__('Target'))
                    ->description(fn (MonthlyPlan $record): HtmlString => new HtmlString(e($record->annualTarget->department->name)
                        .'<span class="rt-phone-only">, '.e($record->ethiopianMonthName().' '.$record->annualTarget->fiscal_year).'</span>'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('ethiopian_month')->label(__('Month'))
                    ->formatStateUsing(fn (MonthlyPlan $record): string => $record->ethiopianMonthName().' '.$record->annualTarget->fiscal_year)
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('planned_value')
                    ->label(__('Monthly target'))
                    ->formatStateUsing(fn (MonthlyPlan $record): string => $record->annualTarget->formatValue($record->planned_value))
                    ->description(fn (MonthlyPlan $record): string => $record->annualTarget->measurement_unit)
                    ->alignEnd()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('actual')
                    ->label(__('Actual'))
                    ->state(fn (MonthlyPlan $record): string => $record->annualTarget->formatValue(app(TargetProgress::class)->monthActual($record)))
                    ->alignEnd()
                    ->visibleFrom('md'),
                TextColumn::make('achievement')
                    ->label(__('Achieved'))
                    ->state(function (MonthlyPlan $record): string {
                        $achievement = TargetProgress::achievement(app(TargetProgress::class)->monthActual($record), $record->planned_value);

                        return $achievement === null ? '—' : $achievement.'%';
                    })
                    ->alignEnd(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['annualTarget.department', 'annualTarget.monthlyPlans']))
            ->defaultSort('ethiopian_month')
            ->filters([
                SelectFilter::make('ethiopian_month')
                    ->label(__('Month'))
                    ->options(EthiopianCalendar::monthOptions())
                    ->default(fn (): int => app(ReportingCalendar::class)->today['month']),
                SelectFilter::make('fiscal_year')
                    ->label(__('Year (E.C.)'))
                    ->options(fn (): array => AnnualTarget::query()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year', 'fiscal_year')->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $year) => $query->whereHas('annualTarget', fn (Builder $target) => $target->where('fiscal_year', $year)),
                    )),
                SelectFilter::make('office')
                    ->label(__('Office'))
                    ->options(fn (): array => OfficeScope::visibleOfficeOptions())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $office) => $query->whereHas('annualTarget', fn (Builder $target) => $target->where('department_id', $office)),
                    )),
            ])
            ->recordActions([
                Action::make('openTarget')
                    ->label(__('Annual target'))
                    ->color('gray')
                    ->url(fn (MonthlyPlan $record): string => AnnualTargetResource::getUrl('view', ['record' => $record->annual_target_id])),
                EditAction::make(),
            ])
            ->emptyStateHeading(__('No monthly targets for this month'))
            ->emptyStateDescription(__('Monthly targets come from splitting an annual target across months. Open an annual target and choose "Allocate across months".'));
    }
}
