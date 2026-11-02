<?php

namespace App\Support;

use App\Filament\RichContentBlocks\YouTubeVideoBlock;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Turns article text from the editor into HTML for the public website: photos get their current address, YouTube
 * blocks become the privacy-enhanced player, and everything is cleaned so only ordinary formatting, our own
 * photos and YouTube videos remain. No scripts, styles, forms or other frames.
 */
class PostHtml
{
    /** Where photos placed inside articles are stored. */
    public const ATTACHMENTS_DISK = 'public';

    public const ATTACHMENTS_DIRECTORY = 'posts/images';

    public static function render(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $expanded = RichContentRenderer::make($html)
            ->fileAttachmentsDisk(self::ATTACHMENTS_DISK)
            ->fileAttachmentsVisibility('public')
            ->customBlocks([YouTubeVideoBlock::class])
            ->toUnsafeHtml();

        return self::clean($expanded);
    }

    public static function clean(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $clean = self::sanitizer()->sanitize($html);

        // The sanitizer checks addresses per attribute, not per element: keep frames for YouTube's player only.
        $clean = preg_replace_callback(
            '#<iframe\b[^>]*></iframe>#i',
            fn (array $match): string => preg_match('#\ssrc="https://www\.youtube-nocookie\.com/embed/[A-Za-z0-9_-]{11}"#', $match[0]) ? $match[0] : '',
            $clean,
        );

        // Photos from anywhere else lose their address above; drop what is left of them.
        $clean = preg_replace('#<img\b(?![^>]*\ssrc=)[^>]*>#i', '', $clean);

        return blank(strip_tags($clean, '<img><iframe>')) ? null : $clean;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        static $sanitizers = [];

        // Keyed by the storage address, which tests and the demo may change at runtime.
        $ownHost = parse_url(Storage::disk(self::ATTACHMENTS_DISK)->url('x'), PHP_URL_HOST) ?: 'localhost';

        return $sanitizers[$ownHost] ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('b')
                ->allowElement('em')
                ->allowElement('i')
                ->allowElement('u')
                ->allowElement('s')
                ->allowElement('mark')
                ->allowElement('h2')
                ->allowElement('h3')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('blockquote')
                ->allowElement('hr')
                ->allowElement('a', ['href', 'title'])
                ->allowElement('table')
                ->allowElement('thead')
                ->allowElement('tbody')
                ->allowElement('tr')
                ->allowElement('th', ['colspan', 'rowspan'])
                ->allowElement('td', ['colspan', 'rowspan'])
                ->allowElement('details')
                ->allowElement('div')
                ->allowElement('summary')
                ->allowElement('figure', ['class'])
                ->allowElement('figcaption')
                ->allowElement('img', ['src', 'alt', 'width', 'height'])
                ->allowElement('iframe', ['src', 'title', 'loading', 'allowfullscreen'])
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowMediaSchemes(['https', 'http'])
                ->allowMediaHosts([$ownHost, 'www.youtube-nocookie.com'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->forceAttribute('img', 'loading', 'lazy')
                ->withMaxInputLength(500_000)
        );
    }
}
