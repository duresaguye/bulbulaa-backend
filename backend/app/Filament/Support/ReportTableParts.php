<?php

namespace App\Filament\Support;

use App\Enums\ReportStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Columns and filters every report list uses, so the lists look and behave the same.
 */
class ReportTableParts
{
    /**
     * A second line under a list's first column that only shows on phones, carrying a column hidden there.
     */
    public static function phoneDetail(?string $text): ?HtmlString
    {
        return blank($text) ? null : new HtmlString('<span class="rt-phone-only">'.e($text).'</span>');
    }

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
