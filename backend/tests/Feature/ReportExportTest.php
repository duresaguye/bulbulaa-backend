<?php

namespace Tests\Feature;

use App\Models\MonthlyReport;
use App\Models\WeeklyProgressReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildOrganization();
    }

    private function submittedMonthlyReport(): MonthlyReport
    {
        $report = MonthlyReport::factory()->create(['department_id' => $this->office->id, 'summary' => 'Collected waste from the market area.']);
        $report->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();

        return $report;
    }

    public function test_an_office_can_print_its_own_report(): void
    {
        $report = $this->submittedMonthlyReport();

        $this->actingAs($this->officeHead())
            ->get("/admin/exports/monthly/{$report->id}/print")
            ->assertOk()
            ->assertSee('Sanitation office')
            ->assertSee('Collected waste from the market area.')
            ->assertSee('Print or save as PDF');
    }

    public function test_an_office_can_download_its_report_as_excel(): void
    {
        $report = $this->submittedMonthlyReport();

        $response = $this->actingAs($this->officeHead())->get("/admin/exports/monthly/{$report->id}/excel");

        $response->assertOk()->assertDownload();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_another_office_cannot_print_or_download_the_report(): void
    {
        $report = $this->submittedMonthlyReport();
        $outsider = $this->officeHead($this->otherOffice);

        $this->actingAs($outsider)->get("/admin/exports/monthly/{$report->id}/print")->assertNotFound();
        $this->actingAs($outsider)->get("/admin/exports/monthly/{$report->id}/excel")->assertNotFound();
    }

    public function test_supervisors_cannot_export_a_draft_from_an_office_below_them(): void
    {
        $draft = WeeklyProgressReport::factory()->create(['department_id' => $this->office->id]);

        $this->actingAs($this->supervisor())->get("/admin/exports/weekly/{$draft->id}/print")->assertNotFound();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $report = $this->submittedMonthlyReport();

        $this->get("/admin/exports/monthly/{$report->id}/print")->assertRedirect('/admin/login');
    }

    public function test_unknown_report_types_are_not_found(): void
    {
        $this->actingAs($this->cityPlanner())->get('/admin/exports/quarterly/1/print')->assertNotFound();
    }

    public function test_city_planners_can_export_office_performance(): void
    {
        $this->targetWithPlans($this->office, 1200, [1 => 100, 2 => 100]);

        $this->actingAs($this->cityPlanner())
            ->get('/admin/exports/office-performance/print?year=2018')
            ->assertOk()
            ->assertSee('Sanitation office');

        $this->actingAs($this->cityPlanner())
            ->get('/admin/exports/office-performance/excel?year=2018')
            ->assertOk()
            ->assertDownload();
    }

    public function test_offices_without_oversight_cannot_export_office_performance(): void
    {
        $this->actingAs($this->officeHead())
            ->get('/admin/exports/office-performance/print')
            ->assertForbidden();
    }

    public function test_exports_follow_the_persons_language(): void
    {
        $report = $this->submittedMonthlyReport();
        $user = $this->officeHead();
        $user->forceFill(['locale' => 'om'])->save();

        $this->actingAs($user)
            ->get("/admin/exports/monthly/{$report->id}/print")
            ->assertOk()
            ->assertSee("Maxxansi yookaan PDF tti olkaa'i")
            ->assertSee("Gabaasa ji'aa Fulbaana 2018");
    }

    public function test_excel_files_are_named_in_the_persons_language_with_an_english_fallback(): void
    {
        $report = $this->submittedMonthlyReport();
        $user = $this->officeHead();
        $user->forceFill(['locale' => 'am'])->save();

        $disposition = $this->actingAs($user)
            ->get("/admin/exports/monthly/{$report->id}/excel")
            ->headers->get('Content-Disposition');

        $this->assertStringContainsString("filename=monthly-report-{$report->id}.xlsx", $disposition);
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('sanitation-office-መስከረም-2018.xlsx'), $disposition);
    }

    public function test_printed_dates_have_no_english_month_names(): void
    {
        $report = $this->submittedMonthlyReport();
        $user = $this->officeHead();
        $user->forceFill(['locale' => 'om'])->save();

        $this->actingAs($user)
            ->get("/admin/exports/monthly/{$report->id}/print")
            ->assertOk()
            ->assertSee('Kan maxxanfame')
            ->assertDontSee(now()->format('M Y'))
            ->assertSee(now()->format('d/m/Y'));
    }
}
