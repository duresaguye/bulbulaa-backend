<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Models\AboutSection;
use App\Models\Alert;
use App\Models\Department;
use App\Models\Document;
use App\Models\InvestmentSector;
use App\Models\Project;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\TourismPlace;
use App\Support\PostHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Read-only website content: site settings, projects, services, tourism places, documents, alerts, investment
 * sectors, About page sections and the office directory. Only what staff have
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
     * The single-page settings. A section staff have never saved is null, and the website keeps its own text.
     */
    public function settings(): JsonResponse
    {
        $url = fn (?string $path): ?string => $path ? Storage::disk('public')->url($path) : null;
        $sections = collect(SiteSetting::SECTIONS)->mapWithKeys(fn (string $key): array => [$key => SiteSetting::section($key)]);

        $mayor = $sections['mayor'];
        $contact = $sections['contact'];
        $home = $sections['home'];
        $city = $sections['city'];
        $investment = $sections['investment'];
        $about = $sections['about'];

        return $this->respond([
            'mayor' => $mayor === null ? null : [
                'name' => $mayor['name'] ?? null,
                'title' => self::fallback($mayor['title'] ?? []),
                'quote' => self::fallback($mayor['quote'] ?? []),
                'excerpt' => self::fallback($mayor['excerpt'] ?? []),
                'message' => self::fallback($mayor['message'] ?? []),
                'photo_url' => $url($mayor['photo'] ?? null),
            ],
            'contact' => $contact === null ? null : [
                'phone' => $contact['phone'] ?? null,
                'email' => $contact['email'] ?? null,
                'address' => self::fallback($contact['address'] ?? []),
                'facebook' => $contact['facebook'] ?? null,
                'telegram' => $contact['telegram'] ?? null,
                'youtube' => $contact['youtube'] ?? null,
                'x' => $contact['x'] ?? null,
            ],
            'home' => $home === null ? null : [
                'hero_image_url' => $url($home['hero_image'] ?? null),
                'title' => self::fallback($home['title'] ?? []),
                'subtitle' => self::fallback($home['subtitle'] ?? []),
                'caption' => self::fallback($home['caption'] ?? []),
            ],
            'city' => $city === null ? null : [
                'facts' => collect($city['facts'] ?? [])->map(fn (array $fact): array => ['value' => $fact['value'] ?? '', 'label' => self::fallback($fact['label'] ?? [])])->values()->all(),
                'points' => collect($city['points'] ?? [])->map(fn (array $point): array => self::fallback($point['text'] ?? []))->values()->all(),
            ],
            'investment' => $investment === null ? null : [
                'park_name' => self::fallback($investment['park_name'] ?? []),
                'hectares' => $investment['hectares'] ?? null,
                'investors' => $investment['investors'] ?? null,
                'note' => self::fallback($investment['note'] ?? []),
            ],
            'about' => $about === null ? null : [
                'gallery' => collect($about['gallery'] ?? [])
                    ->filter(fn (array $photo): bool => filled($photo['image'] ?? null))
                    ->map(fn (array $photo): array => ['image_url' => $url($photo['image']), 'caption' => self::fallback($photo['caption'] ?? [])])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function investmentSectors(): JsonResponse
    {
        $sectors = InvestmentSector::query()->published()->orderBy('sort_order')->orderBy('id')->get();

        return $this->respond($sectors->map(fn (InvestmentSector $sector): array => [
            'id' => $sector->id,
            'kind' => $sector->kind,
            'title' => $sector->allTranslations('title'),
            'description' => $sector->allTranslations('description'),
            'items' => collect(['om', 'am', 'en'])->mapWithKeys(fn (string $locale): array => [$locale => $sector->itemsIn($locale)])->all(),
            'image_url' => $sector->image ? Storage::disk('public')->url($sector->image) : null,
        ])->all());
    }

    public function aboutSections(): JsonResponse
    {
        $sections = AboutSection::query()->published()->orderBy('sort_order')->orderBy('id')->get();

        return $this->respond($sections->map(fn (AboutSection $section): array => [
            'id' => $section->id,
            'title' => $section->allTranslations('title'),
            'body' => $section->allTranslations('body'),
        ])->all());
    }

    /**
     * Offices staff chose to publish, with only their public details. Internal names fill in a missing public name.
     */
    public function offices(): JsonResponse
    {
        $offices = Department::query()->where('is_active', true)->where('is_public', true)->orderBy('name')->get();

        return $this->respond($offices->map(fn (Department $office): array => [
            'id' => $office->id,
            'name' => self::fallback(array_filter($office->public_name ?? []) ?: ['om' => $office->name]),
            'description' => $office->allTranslations('public_description'),
            'location' => $office->allTranslations('public_location'),
            'phone' => $office->public_phone,
            'email' => $office->public_email,
        ])->all());
    }

    /**
     * A text in all three languages, empty ones filled from Afaan Oromoo, then from any language that has text.
     *
     * @param  array<string, mixed>  $values
     * @return array{om: string|null, am: string|null, en: string|null}
     */
    private static function fallback(array $values): array
    {
        $first = collect(['om', 'am', 'en'])->map(fn (string $code): mixed => $values[$code] ?? null)->first(fn (mixed $value): bool => filled($value));

        return collect(['om', 'am', 'en'])->mapWithKeys(fn (string $code): array => [$code => filled($values[$code] ?? null) ? $values[$code] : $first])->all();
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
