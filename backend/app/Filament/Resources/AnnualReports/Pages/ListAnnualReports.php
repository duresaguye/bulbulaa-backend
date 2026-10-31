<?php

namespace App\Filament\Resources\AnnualReports\Pages;

use App\Filament\Resources\AnnualReports\AnnualReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnnualReports extends ListRecords
{
    protected static string $resource = AnnualReportResource::class;

    public function getSubheading(): ?string
    {
        return __('Year-end results per office, calculated from approved monthly reports.');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New annual report')),
        ];
    }
}
