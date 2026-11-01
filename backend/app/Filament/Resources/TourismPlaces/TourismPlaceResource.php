<?php

namespace App\Filament\Resources\TourismPlaces;

use App\Filament\Resources\TourismPlaces\Pages\ManageTourismPlaces;
use App\Filament\Support\LanguageTabs;
use App\Filament\Support\WebsiteContentResource;
use App\Models\TourismPlace;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Places to visit around Bulbulaa, for the website's tourism page.
 */
class TourismPlaceResource extends WebsiteContentResource
{
    protected static ?string $model = TourismPlace::class;

    protected static ?string $pluralModelLabel = 'Tourism places';

    protected static ?string $modelLabel = 'tourism place';

    protected static ?string $navigationLabel = 'Tourism';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                LanguageTabs::make(fn (string $locale, bool $required): array => [
                    TextInput::make("name.{$locale}")->label(__('Name'))->required($required)->maxLength(120),
                    TextInput::make("kind.{$locale}")
                        ->label(__('Type of place'))
                        ->placeholder(__('For example: Lake, River, Nature'))
                        ->maxLength(40),
                    Textarea::make("description.{$locale}")->label(__('Description'))->rows(4)->maxLength(600),
                ]),
                FileUpload::make('image')
                    ->label(__('Photo'))
                    ->image()
                    ->imageEditor()
                    ->maxSize(5120)
                    ->disk('public')
                    ->directory('tourism')
                    ->visibility('public')
                    ->helperText(__('A wide landscape photo works best. Up to 5 MB.')),
                Toggle::make('is_published')->label(__('Show on the website')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label(__('Photo'))->disk('public')->imageHeight(48)->square()->visibleFrom('sm'),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->state(fn (TourismPlace $record): string => (string) $record->translated('name', app()->getLocale()))
                    ->description(fn (TourismPlace $record): ?string => $record->translated('kind', app()->getLocale()))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('name', 'like', "%{$search}%"))
                    ->wrap(),
                ToggleColumn::make('is_published')->label(__('On website')),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit')),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ])
            ->emptyStateHeading(__('No tourism places yet'))
            ->emptyStateDescription(__('Add lakes, parks and other places to visit. Until you add one, the website shows its built-in list.'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageTourismPlaces::route('/')];
    }
}
