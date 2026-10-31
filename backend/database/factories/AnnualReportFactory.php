<?php

namespace Database\Factories;

use App\Models\AnnualReport;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnnualReport> */
class AnnualReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'prepared_by' => User::factory(),
            'ethiopian_fiscal_year' => 2018,
            'key_accomplishments' => fake()->paragraph(),
        ];
    }
}
