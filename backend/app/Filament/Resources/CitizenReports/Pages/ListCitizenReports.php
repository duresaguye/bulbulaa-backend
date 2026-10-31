<?php

namespace App\Filament\Resources\CitizenReports\Pages;

use App\Filament\Resources\CitizenReports\CitizenReportResource;
use Filament\Resources\Pages\ListRecords;

class ListCitizenReports extends ListRecords
{
    protected static string $resource = CitizenReportResource::class;

    public function getSubheading(): ?string
    {
        return __('Sent from the contact page of the website. Anonymous senders cannot be contacted, so record what was done in the internal note.');
    }
}
