<?php

namespace App\Filament\Resources\MonthlyReports\Pages;

use App\Filament\Resources\MonthlyReports\MonthlyReportResource;
use App\Filament\Support\Concerns\CreatesOfficeReport;
use Filament\Resources\Pages\CreateRecord;

class CreateMonthlyReport extends CreateRecord
{
    use CreatesOfficeReport;

    protected static string $resource = MonthlyReportResource::class;

    public function getTitle(): string
    {
        return 'New monthly report';
    }
}
