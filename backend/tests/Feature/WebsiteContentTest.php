<?php

namespace Tests\Feature;

use App\Enums\AlertLevel;
use App\Enums\DocumentCategory;
use App\Enums\ProjectStatus;
use App\Enums\ServiceAvailability;
use App\Filament\Resources\Alerts\Pages\ManageAlerts;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Project;
use App\Models\Service;
use App\Models\TourismPlace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class WebsiteContentTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    private function contentManager(): User
    {
        return $this->userWith(['cms.manage']);
    }

    public function test_content_managers_can_open_every_website_section(): void
    {
        $this->actingAs($this->contentManager());

        foreach (['projects', 'projects/create', 'services', 'tourism-places', 'documents', 'alerts'] as $path) {
            $this->get("/admin/{$path}")->assertOk();
        }
    }

    public function test_planning_and_reporting_staff_cannot_open_the_website_sections(): void
    {
        $this->buildOrganization();
        $this->actingAs($this->officeHead());

        foreach (['projects', 'services', 'tourism-places', 'documents', 'alerts'] as $path) {
            $this->get("/admin/{$path}")->assertForbidden();
        }
    }

    public function test_a_project_needs_an_oromo_title_and_starts_hidden(): void
    {
        $this->actingAs($this->contentManager());

        Livewire::test(CreateProject::class)
            ->fillForm(['title' => ['om' => '', 'en' => 'New bridge'], 'status' => ProjectStatus::Planned])
            ->call('create')
            ->assertHasFormErrors(['title.om' => 'required']);

        Livewire::test(CreateProject::class)
            ->fillForm(['title' => ['om' => 'Riqicha haaraa', 'en' => 'New bridge'], 'status' => ProjectStatus::Planned])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::sole();
        $this->assertSame('new-bridge', $project->slug);
        $this->assertFalse($project->is_published);
        $this->assertTrue(AuditLog::where('auditable_type', $project->getMorphClass())->exists());
        $this->getJson('/api/public/projects')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_api_lists_only_published_projects_in_the_chosen_order_with_photo_addresses(): void
    {
        Storage::fake('public', ['url' => 'https://admin.bulbulaa.test/storage']);

        Project::factory()->create(['title' => ['om' => 'Hidden'], 'slug' => 'hidden']);
        Project::factory()->published()->create(['title' => ['om' => 'Second'], 'slug' => 'second', 'sort_order' => 2]);
        Project::factory()->published()->create(['title' => ['om' => 'First'], 'slug' => 'first', 'sort_order' => 1, 'images' => ['projects/a.jpg'], 'status' => ProjectStatus::Completed]);

        $this->getJson('/api/public/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'first')
            ->assertJsonPath('data.0.title.en', 'First')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.image_urls.0', 'https://admin.bulbulaa.test/storage/projects/a.jpg')
            ->assertJsonMissingPath('data.0.body');

        $this->getJson('/api/public/projects/first')->assertOk()->assertJsonPath('data.slug', 'first');
        $this->getJson('/api/public/projects/hidden')->assertNotFound();
    }

    public function test_photos_removed_from_a_project_gallery_are_deleted(): void
    {
        Storage::fake('public');
        $keep = UploadedFile::fake()->image('keep.jpg')->store('projects', 'public');
        $drop = UploadedFile::fake()->image('drop.jpg')->store('projects', 'public');
        $project = Project::factory()->create(['images' => [$keep, $drop]]);

        $project->update(['images' => [$keep]]);

        Storage::disk('public')->assertExists($keep);
        Storage::disk('public')->assertMissing($drop);

        $project->delete();
        Storage::disk('public')->assertMissing($keep);
    }

    public function test_services_and_tourism_places_come_from_the_admin_in_order(): void
    {
        $this->actingAs($this->contentManager());

        Livewire::test(ManageServices::class)
            ->callAction('create', data: [
                'title' => ['om' => 'Hayyama daldalaa', 'en' => 'Business licence'],
                'availability' => ServiceAvailability::Available,
                'icon' => 'Briefcase',
                'is_published' => true,
            ])
            ->assertHasNoActionErrors();

        Service::factory()->create(['title' => ['om' => 'Hidden']]);
        TourismPlace::factory()->published()->create(['name' => ['om' => 'Haroo Langaanoo'], 'sort_order' => 2]);
        TourismPlace::factory()->published()->create(['name' => ['om' => 'Lagaa Bulbulaa'], 'sort_order' => 1]);

        $this->getJson('/api/public/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title.en', 'Business licence')
            ->assertJsonPath('data.0.title.am', 'Hayyama daldalaa')
            ->assertJsonPath('data.0.availability', 'available')
            ->assertJsonPath('data.0.icon', 'Briefcase');

        $this->getJson('/api/public/tourism-places')
            ->assertOk()
            ->assertJsonPath('data.0.name.om', 'Lagaa Bulbulaa')
            ->assertJsonPath('data.1.name.om', 'Haroo Langaanoo');
    }

    public function test_only_published_documents_are_listed_and_can_be_filtered(): void
    {
        Storage::fake('public');

        Document::factory()->published()->create(['title' => ['om' => 'Unka'], 'category' => DocumentCategory::Form, 'file_name' => 'unka.pdf']);
        Document::factory()->published()->create(['title' => ['om' => 'Labsii'], 'category' => DocumentCategory::Proclamation]);
        Document::factory()->create(['title' => ['om' => 'Draft']]);
        Document::factory()->create(['title' => ['om' => 'Later'], 'published_at' => now()->addDay()]);

        $this->getJson('/api/public/documents')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/public/documents?category=form')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title.om', 'Unka')
            ->assertJsonPath('data.0.file_type', 'PDF');

        $this->getJson('/api/public/documents?category=secret')->assertUnprocessable();
    }

    public function test_a_document_file_size_is_recorded_and_its_old_file_removed_when_replaced(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put($first = 'documents/first.pdf', str_repeat('a', 3000));
        Storage::disk('public')->put($second = 'documents/second.pdf', str_repeat('b', 1000));

        $document = Document::factory()->create(['file' => $first]);
        $this->assertSame(3000, $document->file_size);

        $document->update(['file' => $second]);

        Storage::disk('public')->assertMissing($first);
        $this->assertSame(1000, $document->fresh()->file_size);
    }

    public function test_alerts_show_only_while_switched_on_and_within_their_dates(): void
    {
        Alert::factory()->create(['message' => ['om' => 'Showing now'], 'level' => AlertLevel::Urgent]);
        Alert::factory()->create(['message' => ['om' => 'Switched off'], 'is_active' => false]);
        Alert::factory()->create(['message' => ['om' => 'Starts tomorrow'], 'starts_at' => now()->addDay()]);
        Alert::factory()->create(['message' => ['om' => 'Ended'], 'ends_at' => now()->subMinute()]);
        Alert::factory()->create(['message' => ['om' => 'Until tonight'], 'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(5)]);

        $messages = collect($this->getJson('/api/public/alerts')->assertOk()->json('data'))->pluck('message.om')->sort()->values()->all();

        $this->assertSame(['Showing now', 'Until tonight'], $messages);
    }

    public function test_an_alert_cannot_end_before_it_starts(): void
    {
        $this->actingAs($this->contentManager());

        Livewire::test(ManageAlerts::class)
            ->callAction('create', data: [
                'message' => ['om' => 'Bishaan ni dhaabata'],
                'level' => AlertLevel::Warning,
                'starts_at' => now()->addDay()->format('Y-m-d H:i'),
                'ends_at' => now()->format('Y-m-d H:i'),
                'is_active' => true,
            ])
            ->assertHasActionErrors(['ends_at']);
    }
}
