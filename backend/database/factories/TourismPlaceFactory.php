<?php

namespace Database\Factories;

use App\Models\TourismPlace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourismPlace>
 */
class TourismPlaceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => ['om' => $name, 'am' => null, 'en' => $name],
            'kind' => ['om' => 'Haroo', 'am' => null, 'en' => 'Lake'],
            'description' => ['om' => fake()->sentence(12), 'am' => null, 'en' => null],
            'sort_order' => 0,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }
}
