<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans post text so only ordinary formatting reaches the public website: no scripts, styles, forms or frames.
 */
class PostHtml
{
    public static function clean(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        static $sanitizer = null;

        $sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('b')
                ->allowElement('em')
                ->allowElement('i')
                ->allowElement('u')
                ->allowElement('s')
                ->allowElement('h2')
                ->allowElement('h3')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('blockquote')
                ->allowElement('hr')
                ->allowElement('a', ['href', 'title'])
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(200_000)
        );

        return $sanitizer->sanitize($html);
    }
}
