<?php

namespace App\Http\Controllers\Api;

use App\Enums\PostType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only feed of published posts for the public website. Drafts and scheduled posts are never returned.
 */
class PublicPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string'],
            'featured' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $types = collect(explode(',', (string) ($validated['type'] ?? '')))
            ->filter()
            ->map(fn (string $type): ?PostType => PostType::tryFrom($type))
            ->filter();

        $posts = Post::query()
            ->published()
            ->when($types->isNotEmpty(), fn ($query) => $query->whereIn('type', $types->map->value->all()))
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->orderByDesc('published_at')
            ->paginate((int) ($validated['per_page'] ?? 12))
            ->withQueryString();

        return PublicPostResource::collection($posts)
            ->response()
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::query()->published()->where('slug', $slug)->firstOrFail();

        return (new PublicPostResource($post))
            ->withBody()
            ->response()
            ->header('Cache-Control', 'public, max-age=60');
    }
}
