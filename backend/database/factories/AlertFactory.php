<?php

namespace Database\Factories;

use App\Enums\AlertLevel;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'message' => ['om' => fake()->sentence(10), 'am' => null, 'en' => null],
            'level' => AlertLevel::Info,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }
}
