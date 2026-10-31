<?php

namespace App\Filament\Resources\MonthlyReports\Tables;

use App\Filament\Support\ReportTableParts;
use App\Models\MonthlyPlan;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MonthlyReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.name')->label('Office')->searchable()->sortable(),
                TextColumn::make('period')
                    ->label('Period')
                    ->state(fn ($record): string => $record->periodLabel())
                    ->sortable(['ethiopian_fiscal_year', 'ethiopian_month']),
                ReportTableParts::statusColumn(),
                TextColumn::make('preparedBy.name')->label('Prepared by')->toggleable(),
                TextColumn::make('submitted_at')->label('Submitted')->since()->dateTimeTooltip()->placeholder('Not submitted')->sortable(),
            ])
            ->defaultSort(fn ($query) => $query
                ->orderByDesc('ethiopian_fiscal_year')
                ->orderByDesc('ethiopian_month'))
            ->filters([
                ReportTableParts::statusFilter(),
                ReportTableParts::officeFilter(),
                ReportTableParts::yearFilter('monthly_reports'),
                SelectFilter::make('ethiopian_month')->label('Month')->options(MonthlyPlan::ETHIOPIAN_MONTHS),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->emptyStateHeading('No monthly reports yet')
            ->emptyStateDescription('A monthly report consolidates the month\'s weekly reports into results against monthly targets.');
    }
}
