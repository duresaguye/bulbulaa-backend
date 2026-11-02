<?php

namespace Tests\Feature;

use App\Filament\RichContentBlocks\YouTubeVideoBlock;
use App\Models\Post;
use App\Support\PostHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_photos_tables_answers_and_youtube_reach_the_website_but_nothing_unsafe(): void
    {
        Storage::fake('public', ['url' => 'https://admin.bulbulaa.test/storage']);
        UploadedFile::fake()->image('road.jpg')->storeAs('posts/images', 'road.jpg', 'public');

        $html = PostHtml::render(
            '<p>Daandiin <mark>haaraan</mark> banameera.</p>'
            .'<img src="http://old-address/storage/posts/images/road.jpg" data-id="posts/images/road.jpg" alt="Daandii">'
            .'<div data-type="customBlock" data-config=\'{"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ","title":"Banama"}\' data-id="youtube"></div>'
            .'<table><tbody><tr><th><p>Ganda</p></th><td><p>01</p></td></tr></tbody></table>'
            .'<details><summary>Yoom?</summary><div data-type="detailsContent"><p>Har\'a</p></div></details>'
            .'<script>alert(1)</script><p onclick="steal()">Text</p>'
            .'<iframe src="https://evil.example/frame"></iframe><img src="https://evil.example/pixel.png">'
        );

        $this->assertStringContainsString('<mark>haaraan</mark>', $html);
        $this->assertStringContainsString('src="'.Storage::disk('public')->url('posts/images/road.jpg').'"', $html, 'Photos get their current address, not the one saved in the text.');
        $this->assertStringContainsString('<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Banama"', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<details><summary>Yoom?</summary><div><p>Har&#039;a</p></div></details>', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertSame(1, substr_count($html, '<img'), 'The outside picture is removed entirely.');
    }

    public function test_youtube_links_in_every_common_form_are_recognised(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ?t=10',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ&list=x',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ] as $link) {
            $this->assertSame('dQw4w9WgXcQ', YouTubeVideoBlock::videoId($link), $link);
        }

        $this->assertNull(YouTubeVideoBlock::videoId('https://vimeo.com/12345'));
        $this->assertNull(YouTubeVideoBlock::videoId('https://evil.example/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(YouTubeVideoBlock::videoId('https://www.youtube.com/watch?v=<script>'));
    }

    public function test_the_public_post_api_sends_the_rendered_article(): void
    {
        $post = Post::factory()->published()->create([
            'body' => ['om' => '<div data-type="customBlock" data-config=\'{"url":"https://youtu.be/dQw4w9WgXcQ"}\' data-id="youtube"></div>', 'am' => null, 'en' => null],
        ]);

        $this->getJson("/api/public/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.body.om', '<figure class="video"><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="YouTube video" loading="lazy" allowfullscreen></iframe></figure>');
    }
}
