<?php

namespace App\Filament\Resources\WeeklyProgressReports\Pages;

use App\Filament\Resources\WeeklyProgressReports\WeeklyProgressReportResource;
use App\Filament\Support\Concerns\CreatesOfficeReport;
use Filament\Resources\Pages\CreateRecord;

class CreateWeeklyProgressReport extends CreateRecord
{
    use CreatesOfficeReport;

    protected static string $resource = WeeklyProgressReportResource::class;

    public function getTitle(): string
    {
        return __('New weekly report');
    }
}
