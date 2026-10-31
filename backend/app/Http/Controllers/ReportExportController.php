<?php

namespace App\Http\Controllers;

use App\Filament\Pages\OfficePerformance;
use App\Services\EthiopianCalendar;
use App\Support\ReportDocument;
use App\Support\SpreadsheetExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Printable pages (saved as PDF from the browser) and Excel files for reports and the office overview.
 * Every download goes through the same visibility rules as the screens.
 */
class ReportExportController extends Controller
{
    public function printReport(string $type, int $record): View
    {
        $report = ReportDocument::findVisible($type, $record);

        return view('exports.report', ['document' => ReportDocument::forReport($type, $report)]);
    }

    public function excelReport(string $type, int $record): StreamedResponse
    {
        $report = ReportDocument::findVisible($type, $record);

        return SpreadsheetExport::report(ReportDocument::forReport($type, $report));
    }

    public function printOfficePerformance(Request $request): View
    {
        abort_unless(OfficePerformance::canAccess(), 403);

        return view('exports.office-performance', ['document' => $this->officePerformance($request)]);
    }

    public function excelOfficePerformance(Request $request): StreamedResponse
    {
        abort_unless(OfficePerformance::canAccess(), 403);

        return SpreadsheetExport::officePerformance($this->officePerformance($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function officePerformance(Request $request): array
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'unit' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
        ]);

        return ReportDocument::forOfficePerformance(
            (int) ($validated['year'] ?? EthiopianCalendar::today()['year']),
            isset($validated['unit']) ? (int) $validated['unit'] : null,
            (string) ($validated['status'] ?? 'all'),
        );
    }
}
