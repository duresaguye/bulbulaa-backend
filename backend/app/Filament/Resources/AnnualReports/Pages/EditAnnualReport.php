<?php

namespace App\Filament\Resources\AnnualReports\Pages;

use App\Filament\Resources\AnnualReports\AnnualReportResource;
use App\Filament\Support\Concerns\EditsOfficeReport;
use Filament\Resources\Pages\EditRecord;

class EditAnnualReport extends EditRecord
{
    use EditsOfficeReport;

    protected static string $resource = AnnualReportResource::class;

    public function getTitle(): string
    {
        return __('Edit annual report');
    }

    public function getSubheading(): string
    {
        return $this->getRecord()->department->name.', '.$this->getRecord()->periodLabel();
    }
}
