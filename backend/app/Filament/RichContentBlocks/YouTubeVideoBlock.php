<?php

namespace App\Filament\RichContentBlocks;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * A YouTube video inside an article. Editors paste a link; the website shows YouTube's privacy-enhanced player,
 * which sets no cookies until the visitor presses play.
 */
class YouTubeVideoBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'youtube';
    }

    public static function getLabel(): string
    {
        return __('YouTube video');
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedPlayCircle;
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading(__('Add a YouTube video'))
            ->modalDescription(__('Paste the link from the YouTube address bar or its Share button.'))
            ->schema([
                TextInput::make('url')
                    ->label(__('YouTube link'))
                    ->placeholder('https://www.youtube.com/watch?v=…')
                    ->required()
                    ->url()
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (self::videoId((string) $value) === null) {
                            $fail(__('This is not a YouTube video link.'));
                        }
                    }),
                TextInput::make('title')
                    ->label(__('What the video shows'))
                    ->helperText(__('Read out to people using screen readers.'))
                    ->maxLength(150),
            ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        return filled($config['title'] ?? null) ? __('YouTube video').': '.$config['title'] : __('YouTube video');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function toPreviewHtml(array $config): ?string
    {
        $id = self::videoId((string) ($config['url'] ?? ''));

        if ($id === null) {
            return null;
        }

        return '<img src="https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg" alt="" style="width:100%;max-width:28rem;border-radius:0.5rem" />';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): ?string
    {
        $id = self::videoId((string) ($config['url'] ?? ''));

        if ($id === null) {
            return null;
        }

        $title = e(filled($config['title'] ?? null) ? $config['title'] : 'YouTube video');

        return '<figure class="video"><iframe src="https://www.youtube-nocookie.com/embed/'.$id.'" title="'.$title.'" loading="lazy" allowfullscreen></iframe></figure>';
    }

    /**
     * The 11-character video id from any common YouTube link, or null when the link is not a YouTube video.
     */
    public static function videoId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        $id = match (true) {
            $host === 'youtu.be' => ltrim($path, '/'),
            in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com'], true) => match (true) {
                $path === '/watch' => $query['v'] ?? null,
                (bool) preg_match('#^/(embed|shorts|live)/([^/?]+)#', $path, $matches) => $matches[2],
                default => null,
            },
            default => null,
        };

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }
}
