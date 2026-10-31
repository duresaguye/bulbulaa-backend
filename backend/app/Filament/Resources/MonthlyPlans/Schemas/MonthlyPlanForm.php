<?php

namespace App\Filament\Resources\MonthlyPlans\Schemas;

use App\Models\AnnualTarget;
use App\Models\MonthlyPlan;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MonthlyPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('annual_target_id')
                ->label('Annual target')
                ->options(function (): array {
                    $query = AnnualTarget::query()->with('department')->where('is_active', true);
                    $user = auth()->user();

                    if ($user instanceof User && ! $user->hasPermission('reports.targets.manage')) {
                        $query->whereIn('department_id', $user->departments()->select('departments.id'));
                    }

                    return $query->get()
                        ->mapWithKeys(fn (AnnualTarget $target) => [$target->id => "{$target->department->name}: {$target->title} ({$target->fiscal_year})"])
                        ->all();
                })
                ->searchable()
                ->required(),
            Select::make('ethiopian_month')
                ->label('Ethiopian month')
                ->options(MonthlyPlan::ETHIOPIAN_MONTHS)
                ->required(),
            TextInput::make('planned_value')
                ->numeric()
                ->minValue(0)
                ->required(),
            Textarea::make('planned_activities')
                ->columnSpanFull(),
        ]);
    }
}
