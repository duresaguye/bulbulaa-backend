<?php

namespace App\Filament\Support;

use App\Enums\ReportStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;

/**
 * Columns and filters every report list uses, so the lists look and behave the same.
 */
class ReportTableParts
{
    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->sortable();
    }

    public static function statusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label('Status')
            ->options(ReportStatus::class);
    }

    public static function officeFilter(): SelectFilter
    {
        return SelectFilter::make('department_id')
            ->label('Office')
            ->options(fn (): array => OfficeScope::visibleOfficeOptions())
            ->searchable();
    }

    public static function yearFilter(string $table, string $column = 'ethiopian_fiscal_year'): SelectFilter
    {
        return SelectFilter::make($column)
            ->label('Year (E.C.)')
            ->options(fn (): array => DB::table($table)
                ->distinct()
                ->orderByDesc($column)
                ->pluck($column, $column)
                ->all());
    }
}
