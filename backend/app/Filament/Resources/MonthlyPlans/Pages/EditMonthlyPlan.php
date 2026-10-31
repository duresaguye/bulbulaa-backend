<?php

namespace App\Filament\Resources\MonthlyPlans\Pages;

use App\Filament\Resources\MonthlyPlans\MonthlyPlanResource;
use App\Models\AnnualTarget;
use Filament\Resources\Pages\EditRecord;

class EditMonthlyPlan extends EditRecord
{
    protected static string $resource = MonthlyPlanResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(
            MonthlyPlanResource::canManageAnnualTarget(AnnualTarget::findOrFail($data['annual_target_id'])),
            403,
        );

        return $data;
    }
}
