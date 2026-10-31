<?php

namespace App\Http\Resources;

use App\Models\Post;
use App\Support\PostHtml;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A published post as the public website receives it: every text in all three languages
 * (empty languages fall back to Afaan Oromoo) and the full text cleaned of anything unsafe.
 *
 * @mixin Post
 */
class PublicPostResource extends JsonResource
{
    /** Lists leave out the full text to stay small; a single post includes it. */
    private bool $withBody = false;

    public function withBody(): static
    {
        $this->withBody = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $withBody = $this->withBody;

        return [
            'slug' => $this->slug,
            'type' => $this->type->value,
            'type_label' => $this->type->labels(),
            'title' => $this->allTranslations('title'),
            'excerpt' => $this->allTranslations('excerpt'),
            'body' => $this->when($withBody, fn (): array => collect($this->allTranslations('body'))
                ->map(fn (?string $html): ?string => PostHtml::clean($html))
                ->all()),
            'cover_image_url' => $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null,
            'attachment_url' => $this->attachment ? Storage::disk('public')->url($this->attachment) : null,
            'deadline_on' => $this->deadline_on?->toDateString(),
            'featured' => $this->is_featured,
            'published_at' => $this->published_at->toIso8601String(),
        ];
    }
}
