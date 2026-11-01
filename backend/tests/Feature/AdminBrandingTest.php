<?php

namespace Tests\Feature;

use App\Filament\Resources\WeeklyProgressReports\Pages\ListWeeklyProgressReports;
use App\Filament\Support\InitialsAvatarProvider;
use App\Models\AnnualTarget;
use App\Models\User;
use App\Models\WeeklyProgressReport;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class AdminBrandingTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    public function test_the_sign_in_page_shows_the_configured_town_name(): void
    {
        config(['app.town_name' => 'Bulbulaa']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('For Bulbulaa administration staff.');
    }

    public function test_the_sign_in_page_shows_the_photo_of_central_bulbulaa(): void
    {
        config(['app.town_name' => 'Bulbulaa']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('rt-login-photo', false)
            ->assertSee('images/bulbulaa-centre.jpg', false)
            ->assertDontSee('Reven');

        $this->assertFileExists(public_path('images/bulbulaa-centre.jpg'));
    }

    public function test_signed_in_pages_do_not_show_the_sign_in_photo(): void
    {
        $this->buildOrganization();

        $this->actingAs($this->cityPlanner())
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('rt-login-photo', false);
    }

    public function test_avatars_are_drawn_locally_from_initials(): void
    {
        $user = User::factory()->make(['name' => 'Chaltu Abdi Gemechu']);

        $avatar = (new InitialsAvatarProvider)->get($user);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $avatar);
        $this->assertStringContainsString('>CA</text>', base64_decode(substr($avatar, strlen('data:image/svg+xml;base64,'))));
    }

    public function test_the_overview_labels_table_values_for_phone_layouts(): void
    {
        $this->buildOrganization();
        $this->targetWithPlans($this->office, 1200, [1 => 100, 2 => 100]);

        $this->actingAs($this->cityPlanner())
            ->get('/admin/office-performance')
            ->assertOk()
            ->assertSee('rt-table--cards', false)
            ->assertSee('data-label="Overdue"', false);
    }

    public function test_report_lists_repeat_the_period_under_the_office_for_phones(): void
    {
        $this->buildOrganization();
        $report = WeeklyProgressReport::factory()->create(['department_id' => $this->office->id]);
        $report->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();

        $this->actingAs($this->supervisor());

        Livewire::test(ListWeeklyProgressReports::class)
            ->assertCanSeeTableRecords([$report])
            ->assertSeeHtml('rt-phone-only')
            ->assertSee($report->periodLabel());
    }

    public function test_the_demo_seeder_builds_a_year_of_fictional_reports(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(10, AnnualTarget::where('description', 'Fictional demonstration target.')->count());
        $this->assertTrue(WeeklyProgressReport::exists());
        $this->assertTrue(User::where('email', 'admin@demo.test')->exists());
    }

    public function test_the_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->expectException(RuntimeException::class);

        (new DemoSeeder)->run();
    }
}
