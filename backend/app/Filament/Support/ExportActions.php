<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

/**
 * The "Download" menu on reports and the office overview: a print-ready page to save as PDF, and an Excel file.
 */
class ExportActions
{
    /**
     * @param  callable(): string  $printUrl
     * @param  callable(): string  $excelUrl
     */
    public static function group(callable $printUrl, callable $excelUrl): ActionGroup
    {
        return ActionGroup::make([
            Action::make('printPdf')
                ->label(__('Print or save as PDF'))
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn (): string => $printUrl(), shouldOpenInNewTab: true),
            Action::make('downloadExcel')
                ->label(__('Download Excel'))
                ->icon(Heroicon::OutlinedTableCells)
                ->url(fn (): string => $excelUrl()),
        ])
            ->label(__('Download'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->button();
    }
}
