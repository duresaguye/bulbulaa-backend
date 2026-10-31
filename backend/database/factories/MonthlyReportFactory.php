<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\MonthlyReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MonthlyReport> */
class MonthlyReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'prepared_by' => User::factory(),
            'ethiopian_fiscal_year' => 2018,
            'ethiopian_month' => 1,
            'summary' => fake()->paragraph(),
            'missed_target_reasons' => fake()->optional()->paragraph(),
            'corrective_actions' => fake()->optional()->paragraph(),
        ];
    }
}
