<?php

namespace Tests\Feature;

use App\Enums\PostType;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\AuditLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class PostCmsTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    private function contentManager(): User
    {
        return $this->userWith(['cms.manage']);
    }

    public function test_content_managers_can_open_the_posts_screen(): void
    {
        $this->actingAs($this->contentManager())->get('/admin/posts')->assertOk();
    }

    public function test_planning_and_reporting_staff_cannot_manage_posts(): void
    {
        $this->buildOrganization();

        $this->actingAs($this->officeHead())->get('/admin/posts')->assertForbidden();
        $this->actingAs($this->officeHead())->get('/admin/posts/create')->assertForbidden();
    }

    public function test_a_post_needs_an_oromo_title_and_gets_a_web_address_from_its_title(): void
    {
        $this->actingAs($this->contentManager());

        Livewire::test(CreatePost::class)
            ->fillForm(['type' => PostType::News, 'title' => ['om' => '', 'am' => '', 'en' => 'Road opened']])
            ->call('create')
            ->assertHasFormErrors(['title.om' => 'required']);

        Livewire::test(CreatePost::class)
            ->fillForm(['type' => PostType::News, 'title' => ['om' => 'Daandiin banameera', 'am' => '', 'en' => 'Road opened']])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::sole();
        $this->assertSame('road-opened', $post->slug);
        $this->assertNull($post->published_at, 'New posts start as drafts.');
        $this->assertTrue(AuditLog::where('auditable_type', $post->getMorphClass())->where('auditable_id', $post->id)->exists());
    }

    public function test_vacancies_need_a_deadline(): void
    {
        $this->actingAs($this->contentManager());

        Livewire::test(CreatePost::class)
            ->fillForm(['type' => PostType::Vacancy, 'title' => ['om' => 'Beeksisa hojii'], 'deadline_on' => null])
            ->call('create')
            ->assertHasFormErrors(['deadline_on' => 'required']);
    }

    public function test_publishing_from_the_list_puts_the_post_on_the_website(): void
    {
        $post = Post::factory()->create();
        $this->actingAs($this->contentManager());

        $this->getJson("/api/public/posts/{$post->slug}")->assertNotFound();

        Livewire::test(ListPosts::class)->callTableAction('togglePublished', $post);

        $this->assertTrue($post->fresh()->isPublished());
        $this->getJson("/api/public/posts/{$post->slug}")->assertOk();
    }

    public function test_the_website_feed_only_lists_published_posts(): void
    {
        $published = Post::factory()->published()->create();
        $draft = Post::factory()->create();
        $scheduled = Post::factory()->scheduled()->create();

        $this->getJson('/api/public/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $published->slug)
            ->assertHeader('Cache-Control', 'max-age=60, public');

        $this->getJson("/api/public/posts/{$draft->slug}")->assertNotFound();
        $this->getJson("/api/public/posts/{$scheduled->slug}")->assertNotFound();
    }

    public function test_the_feed_can_be_filtered_by_type_and_featured(): void
    {
        Post::factory()->published()->create(['type' => PostType::News, 'is_featured' => true]);
        Post::factory()->published()->create(['type' => PostType::Vacancy]);
        Post::factory()->published()->create(['type' => PostType::Tender]);

        $this->getJson('/api/public/posts?type=vacancy,tender')->assertJsonCount(2, 'data');
        $this->getJson('/api/public/posts?featured=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'news');
    }

    public function test_missing_languages_fall_back_to_oromo(): void
    {
        $post = Post::factory()->published()->create([
            'title' => ['om' => 'Oduu haaraa', 'am' => null, 'en' => 'Fresh news'],
            'type' => PostType::Tender,
        ]);

        $this->getJson("/api/public/posts/{$post->slug}")
            ->assertJsonPath('data.title.om', 'Oduu haaraa')
            ->assertJsonPath('data.title.am', 'Oduu haaraa')
            ->assertJsonPath('data.title.en', 'Fresh news')
            ->assertJsonPath('data.type_label.om', 'Caalbaasii')
            ->assertJsonPath('data.type_label.am', 'ጨረታ');
    }

    public function test_unsafe_html_never_reaches_the_website(): void
    {
        $post = Post::factory()->published()->create([
            'body' => ['om' => '<p onclick="steal()">Hello</p><script>alert(1)</script><a href="javascript:alert(1)">x</a><a href="https://example.com">ok</a>'],
        ]);

        $body = $this->getJson("/api/public/posts/{$post->slug}")->json('data.body.om');

        $this->assertStringContainsString('<p>Hello</p>', $body);
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('href="https://example.com"', $body);
    }

    public function test_lists_leave_out_the_full_text(): void
    {
        Post::factory()->published()->create();

        $this->assertArrayNotHasKey('body', $this->getJson('/api/public/posts')->json('data.0'));
    }
}
