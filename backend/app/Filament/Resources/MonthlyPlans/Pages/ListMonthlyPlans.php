<?php

namespace App\Filament\Resources\MonthlyPlans\Pages;

use App\Filament\Resources\MonthlyPlans\MonthlyPlanResource;
use Filament\Resources\Pages\ListRecords;

class ListMonthlyPlans extends ListRecords
{
    protected static string $resource = MonthlyPlanResource::class;
}
