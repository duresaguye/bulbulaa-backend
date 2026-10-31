<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    public function test_the_admin_opens_in_afaan_oromoo_by_default(): void
    {
        config(['app.locale' => 'om']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="om"', false)
            ->assertSee('Seeni');
    }

    public function test_the_sign_in_page_language_can_be_switched_before_signing_in(): void
    {
        $this->from('/admin/login')
            ->get('/admin/language/am')
            ->assertRedirect('/admin/login');

        $this->get('/admin/login')->assertSee('lang="am"', false);
    }

    public function test_a_signed_in_person_keeps_their_chosen_language(): void
    {
        $this->buildOrganization();
        $user = $this->officeHead();

        $this->actingAs($user)->get('/admin/language/om')->assertRedirect();

        $this->assertSame('om', $user->fresh()->locale);
        $this->actingAs($user->fresh())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Waliigala')
            ->assertSee('Gabaasota torbanii');
    }

    public function test_amharic_shows_ethiopian_month_names_in_amharic(): void
    {
        $this->buildOrganization();
        $user = $this->officeHead();
        $user->forceFill(['locale' => 'am'])->save();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('አጠቃላይ እይታ');
    }

    public function test_unsupported_languages_are_rejected(): void
    {
        $this->get('/admin/language/fr')->assertNotFound();
    }

    public function test_every_phrase_has_an_oromo_and_amharic_translation_with_the_same_placeholders(): void
    {
        $oromo = json_decode(file_get_contents(lang_path('om.json')), true, flags: JSON_THROW_ON_ERROR);
        $amharic = json_decode(file_get_contents(lang_path('am.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(array_keys($oromo), array_keys($amharic));

        foreach ([$oromo, $amharic] as $translations) {
            foreach ($translations as $english => $translated) {
                preg_match_all('/:[a-z]+/', $english, $expected);
                preg_match_all('/:[a-z]+/', $translated, $actual);
                $this->assertEqualsCanonicalizing(array_unique($expected[0]), array_unique($actual[0]), "Placeholders differ for: {$english}");
            }
        }
    }

    public function test_relative_times_read_fully_in_oromo(): void
    {
        Carbon::setLocale('om');

        $this->assertStringEndsWith('dura', Carbon::now()->subDays(3)->diffForHumans());

        Carbon::setLocale('en');
    }

    public function test_new_users_have_no_language_until_they_choose_one(): void
    {
        $this->assertNull(User::factory()->create()->locale);
    }
}
