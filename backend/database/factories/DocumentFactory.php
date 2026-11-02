<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'title' => ['om' => $title, 'am' => null, 'en' => $title],
            'description' => ['om' => fake()->sentence(10), 'am' => null, 'en' => null],
            'category' => DocumentCategory::Form,
            'file' => 'documents/'.fake()->uuid().'.pdf',
            'file_name' => 'form.pdf',
            'file_size' => 120_000,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->subDay()]);
    }
}
