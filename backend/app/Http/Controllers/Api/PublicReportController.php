<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportCategory;
use App\Http\Controllers\Controller;
use App\Models\CitizenReport;
use App\Support\ReportPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Receives messages, complaints, safety concerns and suggestions from the public website.
 */
class PublicReportController extends Controller
{
    public const MAX_PHOTOS = 3;

    public function store(Request $request): JsonResponse
    {
        /** Bots fill every field; people never see this one. Pretend success so bots learn nothing. */
        if (filled($request->input('website'))) {
            return response()->json(['reference' => null], 201);
        }

        /** Answer in the language of the website page the person is using. */
        $locale = in_array($request->input('locale'), ['om', 'am', 'en'], true) ? $request->input('locale') : 'om';
        app()->setLocale($locale);

        $anonymous = $request->boolean('anonymous', true);

        $validated = $request->validate([
            'category' => ['required', Rule::enum(ReportCategory::class)],
            'details' => ['required', 'string', 'min:10', 'max:3000'],
            'location' => ['nullable', 'string', 'max:200'],
            'anonymous' => ['nullable', 'boolean'],
            /** People who give their name may leave contact details; a question needs them so the city can answer. */
            'name' => [Rule::requiredIf(! $anonymous), 'nullable', 'string', 'max:100'],
            'contact' => [
                Rule::requiredIf(! $anonymous && $request->input('category') === ReportCategory::Message->value),
                'nullable', 'string', 'max:150',
            ],
            'locale' => ['nullable', Rule::in(['om', 'am', 'en'])],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
        ], attributes: [
            'category' => __('Type'),
            'details' => __('What happened'),
            'location' => __('Location'),
            'name' => __('Your name'),
            'contact' => __('Phone or email'),
            'photos' => __('Photos'),
            'photos.*' => __('Photo'),
        ]);

        $stored = [];

        try {
            $report = DB::transaction(function () use ($request, $validated, $anonymous, $locale, &$stored): CitizenReport {
                $report = CitizenReport::create([
                    'category' => $validated['category'],
                    'details' => trim($validated['details']),
                    'location' => $validated['location'] ?? null,
                    'is_anonymous' => $anonymous,
                    'reporter_name' => $validated['name'] ?? null,
                    'reporter_contact' => $validated['contact'] ?? null,
                    'locale' => $locale,
                ]);

                foreach ($request->file('photos', []) as $photo) {
                    $stored[] = ReportPhoto::store($photo, 'citizen-reports/'.$report->reference, CitizenReport::PHOTO_DISK);
                }

                $report->update(['photos' => $stored]);

                return $report;
            });
        } catch (Throwable $exception) {
            Storage::disk(CitizenReport::PHOTO_DISK)->delete($stored);

            throw $exception;
        }

        return response()->json(['reference' => $report->reference], 201);
    }
}
