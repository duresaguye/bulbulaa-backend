<?php

namespace App\Filament\Resources\MonthlyReports\Pages;

use App\Filament\Resources\MonthlyReports\MonthlyReportResource;
use App\Filament\Support\Concerns\EditsOfficeReport;
use Filament\Resources\Pages\EditRecord;

class EditMonthlyReport extends EditRecord
{
    use EditsOfficeReport;

    protected static string $resource = MonthlyReportResource::class;

    public function getTitle(): string
    {
        return __('Edit monthly report');
    }

    public function getSubheading(): string
    {
        return $this->getRecord()->department->name.', '.$this->getRecord()->periodLabel();
    }
}
