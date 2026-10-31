<?php

namespace Database\Factories;

use App\Enums\ReportCategory;
use App\Models\CitizenReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CitizenReport>
 */
class CitizenReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category' => ReportCategory::Complaint,
            'details' => fake()->paragraph(),
            'location' => fake()->streetName(),
            'is_anonymous' => true,
            'photos' => [],
            'locale' => 'om',
        ];
    }
}
