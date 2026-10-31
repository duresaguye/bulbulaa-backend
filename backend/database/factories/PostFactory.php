<?php

namespace Database\Factories;

use App\Enums\PostType;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(5);

        return [
            'type' => PostType::News,
            'slug' => fake()->unique()->slug(4),
            'title' => ['om' => $title, 'am' => null, 'en' => $title],
            'excerpt' => ['om' => fake()->sentence(12), 'am' => null, 'en' => null],
            'body' => ['om' => '<p>'.fake()->paragraph().'</p>', 'am' => null, 'en' => null],
            'is_featured' => false,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->subDay()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->addWeek()]);
    }
}
