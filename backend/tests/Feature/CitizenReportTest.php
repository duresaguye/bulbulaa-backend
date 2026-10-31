<?php

namespace Tests\Feature;

use App\Enums\CitizenReportStatus;
use App\Filament\Resources\CitizenReports\Pages\ViewCitizenReport;
use App\Models\CitizenReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class CitizenReportTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(CitizenReport::PHOTO_DISK);
        RateLimiter::clear('report-minute:127.0.0.1');
    }

    private function photoWithGps(): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/photo-with-gps.jpg'), 'IMG_0042.jpg', 'image/jpeg', null, true);
    }

    private function handler(): User
    {
        return $this->userWith(['citizen_reports.manage']);
    }

    public function test_the_public_can_send_an_anonymous_report_with_a_photo_and_gets_a_reference(): void
    {
        $response = $this->post('/api/public/reports', [
            'category' => 'complaint',
            'details' => 'The water pipe near the school is broken.',
            'location' => 'Kebele 02',
            'anonymous' => '1',
            'name' => 'Should not be kept',
            'locale' => 'en',
            'photos' => [$this->photoWithGps()],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonStructure(['reference']);

        $report = CitizenReport::sole();
        $this->assertSame($response->json('reference'), $report->reference);
        $this->assertMatchesRegularExpression('/^BUL-\d{4}-0001$/', $report->reference);
        $this->assertSame(CitizenReportStatus::New, $report->status);
        $this->assertNull($report->reporter_name, 'Anonymous reports keep no name even if one was typed.');
        $this->assertCount(1, $report->photos);
        Storage::disk(CitizenReport::PHOTO_DISK)->assertExists($report->photos[0]);
    }

    public function test_photos_lose_their_gps_position_and_are_turned_upright(): void
    {
        $this->post('/api/public/reports', [
            'category' => 'safety',
            'details' => 'Street light has been off for a week.',
            'photos' => [$this->photoWithGps()],
        ], ['Accept' => 'application/json'])->assertCreated();

        $stored = Storage::disk(CitizenReport::PHOTO_DISK)->path(CitizenReport::sole()->photos[0]);
        $exif = @exif_read_data($stored) ?: [];
        [$width, $height] = getimagesize($stored);

        $this->assertArrayNotHasKey('GPSLatitude', $exif);
        $this->assertArrayNotHasKey('GPSLongitude', $exif);
        $this->assertGreaterThan($width, $height, 'The sideways phone photo is stored upright (portrait).');
    }

    public function test_reports_are_checked_and_errors_come_back_in_the_page_language(): void
    {
        $this->post('/api/public/reports', ['category' => 'message', 'details' => 'short', 'anonymous' => '0', 'locale' => 'am'], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['details', 'name', 'contact'])
            ->assertJsonPath('errors.name.0', 'ስምዎ ያስፈልጋል።');
    }

    public function test_only_images_are_accepted_and_at_most_three(): void
    {
        $this->post('/api/public/reports', [
            'category' => 'complaint',
            'details' => 'Something happened at the market.',
            'photos' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ], ['Accept' => 'application/json'])->assertJsonValidationErrors(['photos.0']);

        $this->post('/api/public/reports', [
            'category' => 'complaint',
            'details' => 'Something happened at the market.',
            'photos' => array_fill(0, 4, UploadedFile::fake()->image('a.jpg')),
        ], ['Accept' => 'application/json'])->assertJsonValidationErrors(['photos']);

        $this->assertSame(0, CitizenReport::count());
    }

    public function test_bots_that_fill_the_hidden_field_are_ignored(): void
    {
        $this->post('/api/public/reports', [
            'category' => 'complaint',
            'details' => 'Buy cheap things at my shop now!',
            'website' => 'https://spam.example',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(0, CitizenReport::count());
    }

    public function test_one_connection_cannot_flood_the_inbox(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->post('/api/public/reports', ['category' => 'suggestion', 'details' => "Suggestion number {$attempt} for the town."], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->post('/api/public/reports', ['category' => 'suggestion', 'details' => 'One suggestion too many.'], ['Accept' => 'application/json'])
            ->assertTooManyRequests();
    }

    public function test_staff_with_permission_can_handle_messages_and_see_photos(): void
    {
        $this->buildOrganization();
        $this->post('/api/public/reports', ['category' => 'complaint', 'details' => 'Broken pipe near the school.', 'photos' => [$this->photoWithGps()]], ['Accept' => 'application/json']);
        $report = CitizenReport::sole();
        $handler = $this->handler();

        $this->actingAs($handler)->get('/admin/citizen-reports')->assertOk()->assertSee('Broken pipe near the school.');
        $this->actingAs($handler)->get("/admin/citizen-reports/{$report->id}/photos/0")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        Livewire::actingAs($handler)
            ->test(ViewCitizenReport::class, ['record' => $report->id])
            ->callAction('handle', ['status' => CitizenReportStatus::Resolved, 'department_id' => $this->office->id, 'staff_note' => 'Fixed by the water office.']);

        $report->refresh();
        $this->assertSame(CitizenReportStatus::Resolved, $report->status);
        $this->assertSame($this->office->id, $report->department_id);
        $this->assertSame($handler->id, $report->handled_by);
    }

    public function test_other_staff_cannot_read_messages_or_photos(): void
    {
        $this->buildOrganization();
        $this->post('/api/public/reports', ['category' => 'safety', 'details' => 'Private concern about a neighbour.', 'photos' => [$this->photoWithGps()]], ['Accept' => 'application/json']);
        $report = CitizenReport::sole();
        $officeHead = $this->officeHead();

        $this->get("/admin/citizen-reports/{$report->id}/photos/0")->assertRedirect('/admin/login');
        $this->actingAs($officeHead)->get('/admin/citizen-reports')->assertForbidden();
        $this->actingAs($officeHead)->get("/admin/citizen-reports/{$report->id}/photos/0")->assertForbidden();
    }

    public function test_photos_are_removed_when_a_message_is_deleted(): void
    {
        $this->post('/api/public/reports', ['category' => 'complaint', 'details' => 'Broken pipe near the school.', 'photos' => [$this->photoWithGps()]], ['Accept' => 'application/json']);
        $report = CitizenReport::sole();
        $path = $report->photos[0];

        $report->delete();

        Storage::disk(CitizenReport::PHOTO_DISK)->assertMissing($path);
    }
}
