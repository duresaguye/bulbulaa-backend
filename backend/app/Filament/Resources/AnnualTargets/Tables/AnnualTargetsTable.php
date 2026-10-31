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
                    ->label('Target')
                    ->description(fn (AnnualTarget $record): string => $record->department->name.($record->parent_id ? ', share of '.$record->parent->department->name : ''))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('fiscal_year')->label('Year')->sortable(),
                TextColumn::make('target_value')
                    ->label('Annual target')
                    ->formatStateUsing(fn (AnnualTarget $record): string => $record->formatValue($record->target_value))
                    ->description(fn (AnnualTarget $record): string => $record->measurement_unit)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('reported')
                    ->label('Reported to date')
                    ->state(fn (AnnualTarget $record): string => $record->formatValue(self::summary($record)['actual']))
                    ->alignEnd(),
                TextColumn::make('achievement')
                    ->label('Achieved')
                    ->state(fn (AnnualTarget $record): string => self::summary($record)['achievement'] === null ? '—' : self::summary($record)['achievement'].'%')
                    ->alignEnd(),
                TextColumn::make('pace')
                    ->label('Pace')
                    ->badge()
                    ->state(fn (AnnualTarget $record): string => TargetProgress::paceLabel(self::summary($record)['status']))
                    ->color(fn (AnnualTarget $record): string => TargetProgress::paceColor(self::summary($record)['status'])),
                TextColumn::make('allocation')
                    ->label('Monthly allocation')
                    ->badge()
                    ->state(fn (AnnualTarget $record): string => self::allocationLabel($record))
                    ->color(fn (AnnualTarget $record): string => self::allocationColor($record)),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['department', 'parent.department', 'monthlyPlans', 'children.monthlyPlans']))
            ->defaultSort('fiscal_year', 'desc')
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Office')
                    ->options(fn (): array => OfficeScope::visibleOfficeOptions())
                    ->searchable(),
                SelectFilter::make('fiscal_year')
                    ->label('Year (E.C.)')
                    ->options(fn (): array => AnnualTarget::query()
                        ->distinct()
                        ->orderByDesc('fiscal_year')
                        ->pluck('fiscal_year', 'fiscal_year')
                        ->all()),
                TernaryFilter::make('is_active')->label('Active')->default(true),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->emptyStateHeading('No annual targets')
            ->emptyStateDescription('The city administration assigns annual targets to offices. Each office then splits its targets across months.');
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
            return 'Not allocated';
        }

        $gap = $target->allocationGap();

        return match (true) {
            $gap === null => $target->monthlyPlans->count().' months set',
            $gap == 0 => 'Matches annual target',
            $gap > 0 => $target->formatValue($gap).' unallocated',
            default => $target->formatValue(abs($gap)).' over target',
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
