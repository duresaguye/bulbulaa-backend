<?php

namespace App\Filament\Resources\Services;

use App\Enums\ServiceAvailability;
use App\Filament\Resources\Services\Pages\ManageServices;
use App\Filament\Support\LanguageTabs;
use App\Filament\Support\WebsiteContentResource;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The city services listed on the website, and whether people can use them yet.
 */
class ServiceResource extends WebsiteContentResource
{
    protected static ?string $model = Service::class;

    protected static ?string $pluralModelLabel = 'Services';

    protected static ?string $modelLabel = 'service';

    protected static ?string $navigationLabel = 'Services';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                LanguageTabs::make(fn (string $locale, bool $required): array => [
                    TextInput::make("title.{$locale}")->label(__('Name'))->required($required)->maxLength(120),
                    Textarea::make("description.{$locale}")->label(__('What it is for'))->rows(3)->maxLength(400),
                ]),
                ToggleButtons::make('availability')
                    ->label(__('Can people use it?'))
                    ->options(ServiceAvailability::class)
                    ->default(ServiceAvailability::ComingSoon)
                    ->inline()
                    ->required(),
                Select::make('icon')
                    ->label(__('Icon'))
                    ->options(collect(Service::ICONS)->map(fn (string $meaning): string => __($meaning))->all())
                    ->default('Briefcase')
                    ->required(),
                TextInput::make('link_url')
                    ->label(__('Link (optional)'))
                    ->url()
                    ->maxLength(255)
                    ->helperText(__('Where people can use or read about the service.')),
                Toggle::make('is_published')->label(__('Show on the website')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Name'))
                    ->state(fn (Service $record): string => (string) $record->translated('title', app()->getLocale()))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('title', 'like', "%{$search}%"))
                    ->wrap(),
                TextColumn::make('availability')->label(__('Availability'))->badge()->visibleFrom('md'),
                ToggleColumn::make('is_published')->label(__('On website')),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit')),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ])
            ->emptyStateHeading(__('No services yet'))
            ->emptyStateDescription(__('List the services residents can get from the city. Until you add one, the website shows its built-in list.'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageServices::route('/')];
    }
}
