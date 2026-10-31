<?php

namespace App\Filament\Resources\WeeklyProgressReports\Pages;

use App\Filament\Resources\WeeklyProgressReports\WeeklyProgressReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyProgressReports extends ListRecords
{
    protected static string $resource = WeeklyProgressReportResource::class;

    public function getTitle(): string
    {
        return 'Weekly reports';
    }

    public function getSubheading(): ?string
    {
        return 'What each office achieved each week against its monthly targets.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New weekly report'),
        ];
    }
}
