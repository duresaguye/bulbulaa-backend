<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    public function getTitle(): string
    {
        return (string) $this->getRecord()->translated('title', app()->getLocale());
    }

    public function getSubheading(): string
    {
        /** @var Post $post */
        $post = $this->getRecord();

        return $post->type->getLabel().', '.PostsTable::statusLabel($post);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnWebsite')
                ->label(__('View on website'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Post $record): string => rtrim((string) config('app.website_url'), '/').'/news/'.$record->slug, shouldOpenInNewTab: true)
                ->visible(fn (Post $record): bool => filled(config('app.website_url')) && $record->isPublished()),
            PostsTable::publishAction()->after(fn () => $this->refreshFormData(['published_at'])),
            DeleteAction::make(),
        ];
    }
}
