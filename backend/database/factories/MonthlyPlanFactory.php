<?php

namespace Database\Factories;

use App\Models\AnnualTarget;
use App\Models\MonthlyPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MonthlyPlan> */
class MonthlyPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'annual_target_id' => AnnualTarget::factory(),
            'ethiopian_month' => fake()->numberBetween(1, 13),
            'planned_value' => fake()->numberBetween(1, 100),
            'planned_activities' => fake()->paragraph(),
        ];
    }
}
