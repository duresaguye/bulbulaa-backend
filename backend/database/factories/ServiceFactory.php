<?php

namespace Database\Factories;

use App\Enums\ServiceAvailability;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'title' => ['om' => $title, 'am' => null, 'en' => $title],
            'description' => ['om' => fake()->sentence(10), 'am' => null, 'en' => null],
            'icon' => 'Briefcase',
            'availability' => ServiceAvailability::ComingSoon,
            'sort_order' => 0,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }
}
