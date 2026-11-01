<?php

namespace App\Filament\Resources\TourismPlaces\Pages;

use App\Filament\Resources\TourismPlaces\TourismPlaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTourismPlaces extends ManageRecords
{
    protected static string $resource = TourismPlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
