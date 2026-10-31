<?php

namespace Tests\Feature;

use App\Filament\Resources\AnnualReports\AnnualReportResource;
use App\Filament\Resources\AnnualTargets\AnnualTargetResource;
use App\Filament\Resources\MonthlyReports\MonthlyReportResource;
use App\Filament\Resources\WeeklyProgressReports\WeeklyProgressReportResource;
use App\Models\AnnualReport;
use App\Models\MonthlyReport;
use App\Models\Role;
use App\Models\User;
use App\Models\WeeklyProgressEntry;
use App\Models\WeeklyProgressReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class AdminPagesRenderTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildOrganization();
    }

    public function test_every_admin_page_renders_for_a_system_administrator(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::factory()->create(['name' => 'system_admin']));
        $admin->departments()->attach($this->city, ['is_primary' => true]);

        $target = $this->targetWithPlans($this->office, 1200, [1 => 80, 2 => 90, 3 => 100]);
        $this->targetWithPlans($this->office, 90, [1 => 85, 2 => 90], 'average');
        $weekly = WeeklyProgressReport::factory()->create(['department_id' => $this->office->id]);
        WeeklyProgressEntry::factory()->create(['weekly_progress_report_id' => $weekly->id, 'monthly_plan_id' => $target->monthlyPlans->first()->id]);
        $monthly = MonthlyReport::factory()->create(['department_id' => $this->office->id]);
        $annual = AnnualReport::factory()->create(['department_id' => $this->office->id]);

        $this->actingAs($admin);

        foreach ([
            '/admin',
            '/admin/office-performance',
            '/admin/review-queue',
            AnnualTargetResource::getUrl('index'),
            AnnualTargetResource::getUrl('create'),
            AnnualTargetResource::getUrl('view', ['record' => $target]),
            AnnualTargetResource::getUrl('edit', ['record' => $target]),
            '/admin/monthly-plans',
            WeeklyProgressReportResource::getUrl('index'),
            WeeklyProgressReportResource::getUrl('create'),
            WeeklyProgressReportResource::getUrl('view', ['record' => $weekly]),
            WeeklyProgressReportResource::getUrl('edit', ['record' => $weekly]),
            MonthlyReportResource::getUrl('index'),
            MonthlyReportResource::getUrl('create'),
            MonthlyReportResource::getUrl('view', ['record' => $monthly]),
            AnnualReportResource::getUrl('index'),
            AnnualReportResource::getUrl('create'),
            AnnualReportResource::getUrl('view', ['record' => $annual]),
            '/admin/departments',
            '/admin/departments/create',
            '/admin/users',
            '/admin/roles',
            '/admin/roles/create',
            '/admin/permissions',
            '/admin/audit-logs',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_allocation_page_renders_for_the_responsible_office(): void
    {
        $target = $this->targetWithPlans($this->office, 1200, [1 => 100]);

        $this->actingAs($this->officeHead())
            ->get(AnnualTargetResource::getUrl('allocate', ['record' => $target]))
            ->assertOk()
            ->assertSee('Allocation check');
    }

    public function test_each_role_gets_its_own_overview(): void
    {
        $this->targetWithPlans($this->office, 1200, [1 => 100]);

        $this->actingAs($this->cityPlanner())->get('/admin')->assertOk()->assertSee('Offices needing attention')->assertSee('Weekly reports in');
        $this->actingAs($this->supervisor())->get('/admin')->assertOk()->assertSee('Offices needing attention');
        $this->actingAs($this->officeHead())->get('/admin')->assertOk()->assertSee('What needs doing')->assertDontSee('Offices needing attention');
        $this->actingAs($this->reportingOfficer())->get('/admin')->assertOk()->assertDontSee('Weekly reports in');
    }

    public function test_review_queue_and_office_performance_are_hidden_from_reporting_officers(): void
    {
        $this->actingAs($this->reportingOfficer());

        $this->get('/admin/review-queue')->assertForbidden();
        $this->get('/admin/office-performance')->assertForbidden();
    }

    public function test_offices_cannot_open_reports_from_offices_outside_their_scope(): void
    {
        $report = WeeklyProgressReport::factory()->submitted()->create(['department_id' => $this->otherOffice->id]);

        $this->actingAs($this->officeHead())
            ->get(WeeklyProgressReportResource::getUrl('view', ['record' => $report]))
            ->assertNotFound();
    }
}
