<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'slug' => fake()->unique()->slug(3),
            'title' => ['om' => $title, 'am' => null, 'en' => $title],
            'category' => ['om' => 'Bu\'uuraalee misoomaa', 'am' => null, 'en' => 'Infrastructure'],
            'summary' => ['om' => fake()->sentence(12), 'am' => null, 'en' => null],
            'status' => ProjectStatus::Ongoing,
            'images' => [],
            'sort_order' => 0,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }
}
