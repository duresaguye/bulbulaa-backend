<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\AboutSections\Pages\ManageAboutSections;
use App\Filament\Resources\InvestmentSectors\Pages\ManageInvestmentSectors;
use App\Filament\Resources\OfficeDirectory\Pages\ManageOfficeDirectory;
use App\Models\AboutSection;
use App\Models\Department;
use App\Models\InvestmentSector;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\WebsiteDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    private function contentManager(): User
    {
        return $this->userWith(['cms.manage']);
    }

    public function test_only_content_managers_can_open_the_new_website_screens(): void
    {
        $this->actingAs($this->contentManager());
        foreach (['site-settings', 'investment-sectors', 'about-sections', 'office-directory'] as $path) {
            $this->get("/admin/{$path}")->assertOk();
        }

        $this->buildOrganization();
        $this->actingAs($this->officeHead());
        foreach (['site-settings', 'investment-sectors', 'about-sections', 'office-directory'] as $path) {
            $this->get("/admin/{$path}")->assertForbidden();
        }
    }

    public function test_the_settings_page_opens_with_the_current_website_text_and_the_api_says_nothing_until_saved(): void
    {
        $this->actingAs($this->contentManager());

        Livewire::test(SiteSettings::class)
            ->assertSchemaStateSet(['mayor.name' => WebsiteDefaults::section('mayor')['name']], 'form');

        $this->getJson('/api/public/settings')->assertOk()->assertJsonPath('data.mayor', null);
    }

    public function test_saving_a_new_mayor_reaches_the_website_with_translations_filled_in(): void
    {
        Storage::fake('public', ['url' => 'https://admin.bulbulaa.test/storage']);
        $this->actingAs($this->contentManager());

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'mayor.name' => 'Aadde Chaltuu Fakkeenyaa',
                'mayor.title' => ['om' => 'Kantiibaa magaalaa', 'am' => '', 'en' => 'City mayor'],
                'mayor.quote' => ['om' => 'Waliin ni guddanna.', 'am' => '', 'en' => ''],
                'mayor.photo' => [UploadedFile::fake()->image('mayor.jpg', 400, 500)],
                'contact.phone' => '+251 22 000 0000',
                'contact.facebook' => 'https://facebook.com/example',
            ], 'form')
            ->call('save')
            ->assertHasNoFormErrors([], 'form');

        $this->getJson('/api/public/settings')
            ->assertOk()
            ->assertJsonPath('data.mayor.name', 'Aadde Chaltuu Fakkeenyaa')
            ->assertJsonPath('data.mayor.title.en', 'City mayor')
            ->assertJsonPath('data.mayor.title.am', 'Kantiibaa magaalaa')
            ->assertJsonPath('data.mayor.quote.en', 'Waliin ni guddanna.')
            ->assertJsonPath('data.contact.phone', '+251 22 000 0000')
            ->assertJsonPath('data.contact.facebook', 'https://facebook.com/example');

        $photoUrl = $this->getJson('/api/public/settings')->json('data.mayor.photo_url');
        $this->assertStringStartsWith('https://admin.bulbulaa.test/storage/site/', $photoUrl);
    }

    public function test_a_replaced_mayor_photo_is_deleted(): void
    {
        Storage::fake('public');
        UploadedFile::fake()->image('old.jpg')->storeAs('site', 'old.jpg', 'public');
        SiteSetting::store('mayor', [...WebsiteDefaults::section('mayor'), 'photo' => 'site/old.jpg']);

        $this->actingAs($this->contentManager());

        Livewire::test(SiteSettings::class)
            ->fillForm(['mayor.photo' => [UploadedFile::fake()->image('new.jpg', 400, 500)]], 'form')
            ->call('save')
            ->assertHasNoFormErrors([], 'form');

        Storage::disk('public')->assertMissing('site/old.jpg');
    }

    public function test_city_facts_need_a_figure_and_an_oromo_label(): void
    {
        $this->actingAs($this->contentManager());

        Livewire::test(SiteSettings::class)
            ->fillForm(['city.facts' => [['value' => '', 'label' => ['om' => '', 'am' => '', 'en' => 'Lakes']]]], 'form')
            ->call('save')
            ->assertHasFormErrors(['city.facts.0.value' => 'required', 'city.facts.0.label.om' => 'required'], 'form');
    }

    public function test_investment_and_about_lists_can_start_from_the_current_website_text(): void
    {
        $this->actingAs($this->contentManager());

        Livewire::test(ManageInvestmentSectors::class)->callAction('importWebsiteText');
        Livewire::test(ManageAboutSections::class)->callAction('importWebsiteText');

        $this->assertSame(count(WebsiteDefaults::section('investment_sectors')), InvestmentSector::count());
        $this->assertSame(count(WebsiteDefaults::section('about_sections')), AboutSection::count());
        $this->assertFalse(InvestmentSector::query()->where('is_published', true)->exists(), 'Copied items wait as hidden drafts.');
        $this->getJson('/api/public/investment-sectors')->assertJsonCount(0, 'data');

        InvestmentSector::query()->where('kind', 'tourism')->update(['is_published' => true]);

        $this->getJson('/api/public/investment-sectors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'tourism');

        Livewire::test(ManageInvestmentSectors::class)->assertActionHidden('importWebsiteText');
    }

    public function test_the_office_directory_publishes_only_chosen_offices_and_only_their_public_details(): void
    {
        $this->buildOrganization();
        $this->office->forceFill(['is_public' => true, 'public_phone' => '+251 22 111 1111', 'public_location' => ['om' => 'Gamoo bulchiinsaa']])->save();

        $this->actingAs($this->contentManager());
        Livewire::test(ManageOfficeDirectory::class)
            ->assertCanSeeTableRecords([$this->office])
            ->assertActionDoesNotExist('create');

        $response = $this->getJson('/api/public/offices')->assertOk()->assertJsonCount(1, 'data');
        $response
            ->assertJsonPath('data.0.name.om', $this->office->name)
            ->assertJsonPath('data.0.name.en', $this->office->name)
            ->assertJsonPath('data.0.phone', '+251 22 111 1111')
            ->assertJsonPath('data.0.location.en', 'Gamoo bulchiinsaa')
            ->assertJsonMissingPath('data.0.code');

        Department::query()->update(['is_public' => false]);
        $this->getJson('/api/public/offices')->assertJsonCount(0, 'data');
    }
}
