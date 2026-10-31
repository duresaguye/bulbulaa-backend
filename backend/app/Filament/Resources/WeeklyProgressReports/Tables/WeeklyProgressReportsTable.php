<?php

namespace App\Filament\Resources\WeeklyProgressReports\Tables;

use App\Filament\Support\ReportTableParts;
use App\Services\EthiopianCalendar;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class WeeklyProgressReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.name')
                    ->label(__('Office'))
                    ->wrap()
                    ->description(fn ($record): ?HtmlString => ReportTableParts::phoneDetail($record->periodLabel()))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period')
                    ->label(__('Period'))
                    ->state(fn ($record): string => $record->periodLabel())
                    ->visibleFrom('md')
                    ->sortable(['ethiopian_fiscal_year', 'ethiopian_month', 'week_number']),
                ReportTableParts::statusColumn(),
                TextColumn::make('preparedBy.name')->label(__('Prepared by'))->toggleable()->visibleFrom('lg'),
                TextColumn::make('submitted_at')->label(__('Submitted'))->since()->dateTimeTooltip()->placeholder(__('Not submitted'))->sortable()->visibleFrom('md'),
            ])
            ->defaultSort(fn ($query) => $query
                ->orderByDesc('ethiopian_fiscal_year')
                ->orderByDesc('ethiopian_month')
                ->orderByDesc('week_number'))
            ->filters([
                ReportTableParts::statusFilter(),
                ReportTableParts::officeFilter(),
                ReportTableParts::yearFilter('weekly_progress_reports'),
                SelectFilter::make('ethiopian_month')->label(__('Month'))->options(EthiopianCalendar::monthOptions()),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->emptyStateHeading(__('No weekly reports yet'))
            ->emptyStateDescription(__('Weekly reports record what each office achieved against its monthly targets.'));
    }
}
