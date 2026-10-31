<?php

namespace App\Filament\Resources\MonthlyPlans\Pages;

use App\Filament\Resources\MonthlyPlans\MonthlyPlanResource;
use App\Models\AnnualTarget;
use Filament\Resources\Pages\CreateRecord;

class CreateMonthlyPlan extends CreateRecord
{
    protected static string $resource = MonthlyPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(
            MonthlyPlanResource::canManageAnnualTarget(AnnualTarget::findOrFail($data['annual_target_id'])),
            403,
        );

        return $data;
    }
}
