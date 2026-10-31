<?php

namespace App\Filament\Resources\AnnualTargets\Tables;

use App\Filament\Support\OfficeScope;
use App\Models\AnnualTarget;
use App\Services\OfficeOverview;
use App\Services\TargetProgress;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AnnualTargetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Target'))
                    ->description(fn (AnnualTarget $record): string => $record->parent_id ? __(':office, share of :parent', ['office' => $record->department->name, 'parent' => $record->parent->department->name]) : $record->department->name)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('fiscal_year')->label(__('Year'))->sortable()->visibleFrom('md'),
                TextColumn::make('target_value')
                    ->label(__('Annual target'))
                    ->formatStateUsing(fn (AnnualTarget $record): string => $record->formatValue($record->target_value))
                    ->description(fn (AnnualTarget $record): string => $record->measurement_unit)
                    ->alignEnd()
                    ->sortable()
                    ->visibleFrom('lg'),
                TextColumn::make('reported')
                    ->label(__('Reported to date'))
                    ->state(fn (AnnualTarget $record): string => $record->formatValue(self::summary($record)['actual']))
                    ->alignEnd()
                    ->visibleFrom('lg'),
                TextColumn::make('achievement')
                    ->label(__('Achieved'))
                    ->state(fn (AnnualTarget $record): string => self::summary($record)['achievement'] === null ? '—' : self::summary($record)['achievement'].'%')
                    ->alignEnd()
                    ->visibleFrom('sm'),
                TextColumn::make('pace')
                    ->label(__('Pace'))
                    ->badge()
                    ->state(fn (AnnualTarget $record): string => TargetProgress::paceLabel(self::summary($record)['status']))
                    ->color(fn (AnnualTarget $record): string => TargetProgress::paceColor(self::summary($record)['status'])),
                TextColumn::make('allocation')
                    ->label(__('Monthly allocation'))
                    ->badge()
                    ->state(fn (AnnualTarget $record): string => self::allocationLabel($record))
                    ->color(fn (AnnualTarget $record): string => self::allocationColor($record))
                    ->visibleFrom('xl'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['department', 'parent.department', 'monthlyPlans', 'children.monthlyPlans']))
            ->defaultSort('fiscal_year', 'desc')
            ->filters([
                SelectFilter::make('department_id')
                    ->label(__('Office'))
                    ->options(fn (): array => OfficeScope::visibleOfficeOptions())
                    ->searchable(),
                SelectFilter::make('fiscal_year')
                    ->label(__('Year (E.C.)'))
                    ->options(fn (): array => AnnualTarget::query()
                        ->distinct()
                        ->orderByDesc('fiscal_year')
                        ->pluck('fiscal_year', 'fiscal_year')
                        ->all()),
                TernaryFilter::make('is_active')->label(__('Active'))->default(true),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->emptyStateHeading(__('No annual targets'))
            ->emptyStateDescription(__('The city administration assigns annual targets to offices. Each office then splits its targets across months.'));
    }

    /**
     * @return array{target: float, actual: float|null, remaining: float|null, achievement: float|null, expected: float|null, pace: float|null, status: string}
     */
    public static function summary(AnnualTarget $target): array
    {
        return once(fn () => app(TargetProgress::class)->summary($target, self::asOfMonth($target->fiscal_year)));
    }

    /**
     * The last completed month of the target's year: every month for past years, none for future years.
     */
    public static function asOfMonth(int $fiscalYear): int
    {
        return app(OfficeOverview::class)->asOfMonth($fiscalYear);
    }

    public static function allocationLabel(AnnualTarget $target): string
    {
        if ($target->monthlyPlans->isEmpty()) {
            return __('Not allocated');
        }

        $gap = $target->allocationGap();

        return match (true) {
            $gap === null => trans_choice(':count month set|:count months set', $target->monthlyPlans->count()),
            $gap == 0 => __('Matches annual target'),
            $gap > 0 => __(':value unallocated', ['value' => $target->formatValue($gap)]),
            default => __(':value over target', ['value' => $target->formatValue(abs($gap))]),
        };
    }

    public static function allocationColor(AnnualTarget $target): string
    {
        $gap = $target->allocationGap();

        return match (true) {
            $target->monthlyPlans->isEmpty() => 'gray',
            $gap === null, $gap == 0 => 'success',
            default => 'warning',
        };
    }
}
