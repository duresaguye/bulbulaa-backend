<?php

namespace App\Filament\Resources\MonthlyReports\Pages;

use App\Filament\Resources\MonthlyReports\MonthlyReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMonthlyReports extends ListRecords
{
    protected static string $resource = MonthlyReportResource::class;

    public function getSubheading(): ?string
    {
        return 'Each office\'s consolidated results for the month, with explanations for missed targets.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New monthly report'),
        ];
    }
}
