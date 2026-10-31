<?php

namespace Database\Factories;

use App\Models\AnnualTarget;
use App\Models\MonthlyPlan;
use App\Models\WeeklyProgressEntry;
use App\Models\WeeklyProgressReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeeklyProgressEntry> */
class WeeklyProgressEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'weekly_progress_report_id' => WeeklyProgressReport::factory(),
            'monthly_plan_id' => function (array $attributes): int {
                $report = WeeklyProgressReport::findOrFail($attributes['weekly_progress_report_id']);
                $target = AnnualTarget::factory()->create([
                    'department_id' => $report->department_id,
                    'fiscal_year' => $report->ethiopian_fiscal_year,
                ]);

                return MonthlyPlan::factory()->create([
                    'annual_target_id' => $target->id,
                    'ethiopian_month' => $report->ethiopian_month,
                ])->id;
            },
            'actual_value' => fake()->numberBetween(1, 100),
            'progress_summary' => fake()->paragraph(),
        ];
    }
}
