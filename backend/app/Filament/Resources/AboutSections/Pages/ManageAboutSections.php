<?php

namespace App\Filament\Resources\AboutSections\Pages;

use App\Filament\Resources\AboutSections\AboutSectionResource;
use App\Filament\Support\ImportWebsiteTextAction;
use App\Models\AboutSection;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAboutSections extends ManageRecords
{
    protected static string $resource = AboutSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportWebsiteTextAction::make(AboutSection::class, 'about_sections'),
            CreateAction::make(),
        ];
    }
}
