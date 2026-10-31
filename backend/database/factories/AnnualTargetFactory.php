<?php

namespace Database\Factories;

use App\Models\AnnualTarget;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnnualTarget> */
class AnnualTargetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'fiscal_year' => 2018,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'measurement_unit' => 'Households',
            'target_value' => fake()->numberBetween(10, 1000),
            'is_active' => true,
        ];
    }
}
