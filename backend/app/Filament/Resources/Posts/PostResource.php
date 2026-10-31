<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Models\Post;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * News, announcements, vacancies and tenders for the public website. Only content managers can use it.
 */
class PostResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Post::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $pluralModelLabel = 'Posts';

    protected static ?string $modelLabel = 'post';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Posts';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('cms.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('cms.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('cms.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('cms.manage') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
