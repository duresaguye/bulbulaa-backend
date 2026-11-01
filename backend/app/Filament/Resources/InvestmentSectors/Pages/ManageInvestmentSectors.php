<?php

namespace App\Filament\Resources\InvestmentSectors\Pages;

use App\Filament\Resources\InvestmentSectors\InvestmentSectorResource;
use App\Filament\Support\ImportWebsiteTextAction;
use App\Models\InvestmentSector;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInvestmentSectors extends ManageRecords
{
    protected static string $resource = InvestmentSectorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportWebsiteTextAction::make(InvestmentSector::class, 'investment_sectors'),
            CreateAction::make(),
        ];
    }
}
