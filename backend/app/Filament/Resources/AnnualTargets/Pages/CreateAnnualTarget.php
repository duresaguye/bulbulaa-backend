<?php

namespace App\Filament\Resources\AnnualTargets\Pages;

use App\Filament\Resources\AnnualTargets\AnnualTargetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnualTarget extends CreateRecord
{
    protected static string $resource = AnnualTargetResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('Assign annual target');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['revision_reason']);
        $data['assigned_by'] = auth()->id();

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('Target assigned');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
