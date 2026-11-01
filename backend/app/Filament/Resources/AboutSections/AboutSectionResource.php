<?php

namespace App\Filament\Resources\AboutSections;

use App\Filament\Resources\AboutSections\Pages\ManageAboutSections;
use App\Filament\Support\LanguageTabs;
use App\Filament\Support\WebsiteContentResource;
use App\Models\AboutSection;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * The text blocks on the About page: what Bulbulaa is, its history, people, economy and so on.
 */
class AboutSectionResource extends WebsiteContentResource
{
    protected static ?string $model = AboutSection::class;

    protected static ?string $pluralModelLabel = 'About page';

    protected static ?string $modelLabel = 'section';

    protected static ?string $navigationLabel = 'About page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                LanguageTabs::make(fn (string $locale, bool $required): array => [
                    TextInput::make("title.{$locale}")->label(__('Heading'))->required($required)->maxLength(120),
                    Textarea::make("body.{$locale}")->label(__('Text'))->rows(6)->maxLength(3000),
                ]),
                Toggle::make('is_published')->label(__('Show on the website')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Heading'))
                    ->state(fn (AboutSection $record): string => (string) $record->translated('title', app()->getLocale()))
                    ->description(fn (AboutSection $record): string => str((string) $record->translated('body', app()->getLocale()))->limit(110)->toString())
                    ->wrap(),
                ToggleColumn::make('is_published')->label(__('On website')),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit')),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ])
            ->emptyStateHeading(__('No About page sections yet'))
            ->emptyStateDescription(__('Until you add one, the website shows its built-in text. Use "Start from the current website text" to edit that text here.'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageAboutSections::route('/')];
    }
}
