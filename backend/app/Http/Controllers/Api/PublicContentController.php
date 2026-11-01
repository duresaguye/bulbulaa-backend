<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Document;
use App\Models\Project;
use App\Models\Service;
use App\Models\TourismPlace;
use App\Support\PostHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Read-only website content: projects, services, tourism places, documents and alerts. Only what staff have
 * published is returned; every text comes in all three languages, falling back to Afaan Oromoo.
 */
class PublicContentController extends Controller
{
    public function projects(): JsonResponse
    {
        $projects = Project::query()->published()->orderBy('sort_order')->orderByDesc('id')->get();

        return $this->respond($projects->map(fn (Project $project): array => $this->projectData($project))->all());
    }

    public function project(string $slug): JsonResponse
    {
        $project = Project::query()->published()->where('slug', $slug)->firstOrFail();

        return $this->respond($this->projectData($project, withBody: true));
    }

    public function services(): JsonResponse
    {
        $services = Service::query()->published()->orderBy('sort_order')->orderBy('id')->get();

        return $this->respond($services->map(fn (Service $service): array => [
            'id' => $service->id,
            'title' => $service->allTranslations('title'),
            'description' => $service->allTranslations('description'),
            'icon' => $service->icon,
            'availability' => $service->availability->value,
            'availability_label' => $service->availability->labels(),
            'link_url' => $service->link_url,
        ])->all());
    }

    public function tourismPlaces(): JsonResponse
    {
        $places = TourismPlace::query()->published()->orderBy('sort_order')->orderBy('id')->get();

        return $this->respond($places->map(fn (TourismPlace $place): array => [
            'id' => $place->id,
            'name' => $place->allTranslations('name'),
            'kind' => $place->allTranslations('kind'),
            'description' => $place->allTranslations('description'),
            'image_url' => $place->image ? Storage::disk('public')->url($place->image) : null,
        ])->all());
    }

    public function documents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::enum(DocumentCategory::class)],
        ]);

        $documents = Document::query()
            ->published()
            ->when($validated['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->orderByDesc('published_at')
            ->get();

        return $this->respond($documents->map(fn (Document $document): array => [
            'id' => $document->id,
            'title' => $document->allTranslations('title'),
            'description' => $document->allTranslations('description'),
            'category' => $document->category->value,
            'category_label' => $document->category->labels(),
            'file_url' => Storage::disk(Document::DISK)->url($document->file),
            'file_type' => $document->fileType(),
            'file_size' => $document->file_size,
            'published_at' => $document->published_at->toIso8601String(),
        ])->all());
    }

    public function alerts(): JsonResponse
    {
        $alerts = Alert::query()->current()->latest()->get();

        return $this->respond($alerts->map(fn (Alert $alert): array => [
            'id' => $alert->id,
            'message' => $alert->allTranslations('message'),
            'level' => $alert->level->value,
            'link_url' => $alert->link_url,
            'link_label' => $alert->allTranslations('link_label'),
            'ends_at' => $alert->ends_at?->toIso8601String(),
        ])->all(), maxAge: 30);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectData(Project $project, bool $withBody = false): array
    {
        return [
            'slug' => $project->slug,
            'title' => $project->allTranslations('title'),
            'category' => $project->allTranslations('category'),
            'summary' => $project->allTranslations('summary'),
            'status' => $project->status->value,
            'status_label' => $project->status->labels(),
            'image_urls' => collect($project->images ?? [])->map(fn (string $path): string => Storage::disk('public')->url($path))->values()->all(),
            ...($withBody ? ['body' => collect($project->allTranslations('body'))->map(fn (?string $html): ?string => PostHtml::render($html))->all()] : []),
        ];
    }

    private function respond(array $data, int $maxAge = 60): JsonResponse
    {
        return response()->json(['data' => $data])->header('Cache-Control', "public, max-age={$maxAge}");
    }
}
