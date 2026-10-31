<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Department;
use App\Models\User;
use App\Models\WeeklyProgressReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeeklyProgressReport> */
class WeeklyProgressReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'prepared_by' => User::factory(),
            'ethiopian_fiscal_year' => 2018,
            'ethiopian_month' => 1,
            'week_number' => 1,
            'achievements' => fake()->paragraph(),
            'challenges' => fake()->optional()->paragraph(),
            'next_steps' => fake()->optional()->paragraph(),
        ];
    }

    public function submitted(): static
    {
        return $this->afterCreating(function ($report): void {
            $report->forceFill([
                'status' => ReportStatus::Submitted,
                'submitted_by' => $report->submitted_by ?? $report->prepared_by,
                'submitted_at' => now(),
            ])->save();
        });
    }
}
