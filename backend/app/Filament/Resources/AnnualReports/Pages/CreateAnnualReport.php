<?php

namespace App\Filament\Resources\AnnualReports\Pages;

use App\Filament\Resources\AnnualReports\AnnualReportResource;
use App\Filament\Support\Concerns\CreatesOfficeReport;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnualReport extends CreateRecord
{
    use CreatesOfficeReport;

    protected static string $resource = AnnualReportResource::class;

    public function getTitle(): string
    {
        return __('New annual report');
    }
}
