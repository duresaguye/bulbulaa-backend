<?php

namespace App\Filament\Resources\WeeklyProgressReports\Pages;

use App\Filament\Resources\WeeklyProgressReports\WeeklyProgressReportResource;
use App\Filament\Support\Concerns\EditsOfficeReport;
use Filament\Resources\Pages\EditRecord;

class EditWeeklyProgressReport extends EditRecord
{
    use EditsOfficeReport;

    protected static string $resource = WeeklyProgressReportResource::class;

    public function getTitle(): string
    {
        return 'Edit weekly report';
    }

    public function getSubheading(): string
    {
        return $this->getRecord()->department->name.', '.$this->getRecord()->periodLabel();
    }
}
