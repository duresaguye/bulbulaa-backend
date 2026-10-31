<?php

namespace App\Filament\Resources\AnnualTargets\Pages;

use App\Filament\Resources\AnnualTargets\AnnualTargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnnualTargets extends ListRecords
{
    protected static string $resource = AnnualTargetResource::class;

    public function getSubheading(): ?string
    {
        return 'Every target has a responsible office, a unit, a year, and a monthly allocation. Pace compares results with what the allocation expected by the end of last month.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Assign annual target'),
        ];
    }
}
