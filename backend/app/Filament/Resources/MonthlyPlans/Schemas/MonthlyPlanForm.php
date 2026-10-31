<?php

namespace App\Filament\Resources\MonthlyPlans\Schemas;

use App\Models\AnnualTarget;
use App\Models\User;
use App\Services\EthiopianCalendar;
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
                ->label(__('Annual target'))
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
                ->label(__('Ethiopian month'))
                ->options(EthiopianCalendar::monthOptions())
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
