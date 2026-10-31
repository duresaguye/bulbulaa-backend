<?php

namespace App\Http\Controllers;

use App\Filament\Resources\CitizenReports\CitizenReportResource;
use App\Models\CitizenReport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shows a photo attached to a message from the public. Photos are private: only staff who handle messages can see them.
 */
class CitizenReportPhotoController extends Controller
{
    public function show(CitizenReport $report, int $index): StreamedResponse
    {
        abort_unless(CitizenReportResource::canView($report), 403);

        $path = $report->photos[$index] ?? null;
        abort_if($path === null || ! Storage::disk(CitizenReport::PHOTO_DISK)->exists($path), 404);

        return Storage::disk(CitizenReport::PHOTO_DISK)->response($path, headers: [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
