<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\PostType;
use App\Filament\Support\ReportTableParts;
use App\Models\Post;
use App\Services\EthiopianCalendar;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->state(fn (Post $record): string => (string) $record->translated('title', app()->getLocale()))
                    ->description(fn (Post $record): ?HtmlString => ReportTableParts::phoneDetail($record->type->getLabel().', '.self::statusLabel($record)))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('title', 'like', "%{$search}%"))
                    ->wrap(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->state(fn (Post $record): string => self::statusLabel($record))
                    ->color(fn (Post $record): string => match (true) {
                        $record->isPublished() => 'success',
                        $record->isScheduled() => 'info',
                        default => 'gray',
                    })
                    ->visibleFrom('md'),
                TextColumn::make('published_at')
                    ->label(__('Publish date'))
                    ->formatStateUsing(fn ($state): string => EthiopianCalendar::display($state, withTime: true))
                    ->placeholder('—')
                    ->sortable()
                    ->visibleFrom('lg'),
                IconColumn::make('featured')
                    ->label(__('Featured'))
                    ->state(fn (Post $record): ?bool => $record->is_featured ?: null)
                    ->icon(Heroicon::Star)
                    ->color('warning')
                    ->visibleFrom('lg'),
                TextColumn::make('editor.name')
                    ->label(__('Last edited by'))
                    ->placeholder('—')
                    ->visibleFrom('2xl'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')->label(__('Type'))->options(PostType::class),
                SelectFilter::make('state')
                    ->label(__('Status'))
                    ->options([
                        'draft' => __('Draft'),
                        'scheduled' => __('Scheduled'),
                        'published' => __('Published'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'draft' => $query->whereNull('published_at'),
                        'scheduled' => $query->where('published_at', '>', now()),
                        'published' => $query->where('published_at', '<=', now()),
                        default => $query,
                    }),
            ])
            ->recordActions([
                self::publishAction()->iconButton()->tooltip(fn (Post $record): string => $record->published_at === null ? __('Publish') : __('Unpublish')),
                EditAction::make()->iconButton()->tooltip(__('Edit')),
            ])
            ->emptyStateHeading(__('No posts yet'))
            ->emptyStateDescription(__('Write news, announcements, vacancies and tenders here. Published posts appear on the website.'));
    }

    public static function statusLabel(Post $post): string
    {
        return match (true) {
            $post->isPublished() => __('Published'),
            $post->isScheduled() => __('Scheduled'),
            default => __('Draft'),
        };
    }

    /**
     * Publish a draft now, or take a published post off the website.
     */
    public static function publishAction(): Action
    {
        return Action::make('togglePublished')
            ->label(fn (Post $record): string => $record->published_at === null ? __('Publish') : __('Unpublish'))
            ->icon(fn (Post $record): Heroicon => $record->published_at === null ? Heroicon::OutlinedGlobeAlt : Heroicon::OutlinedEyeSlash)
            ->color(fn (Post $record): string => $record->published_at === null ? 'primary' : 'gray')
            ->requiresConfirmation()
            ->modalHeading(fn (Post $record): string => $record->published_at === null ? __('Publish this post?') : __('Take this post off the website?'))
            ->modalDescription(fn (Post $record): string => $record->published_at === null
                ? __('It appears on the website within a few minutes.')
                : __('It is kept here as a draft and can be published again.'))
            ->action(function (Post $record): void {
                $publishing = $record->published_at === null;
                $record->update(['published_at' => $publishing ? now() : null]);

                Notification::make()->success()->title($publishing ? __('Post published') : __('Post unpublished'))->send();
            });
    }
}
