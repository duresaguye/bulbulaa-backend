<?php

namespace Tests\Feature;

use App\Filament\Support\InitialsAvatarProvider;
use App\Models\AnnualTarget;
use App\Models\User;
use App\Models\WeeklyProgressReport;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
