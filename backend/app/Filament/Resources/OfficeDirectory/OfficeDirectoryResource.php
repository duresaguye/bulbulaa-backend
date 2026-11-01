<?php

namespace App\Filament\Resources\OfficeDirectory;

use App\Filament\Resources\OfficeDirectory\Pages\ManageOfficeDirectory;
use App\Filament\Support\LanguageTabs;
use App\Filament\Support\WebsiteContentResource;
use App\Models\Department;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The public list of city offices on the website. Uses the offices already set up for planning and reporting;
 * staff choose which to show and add the public details. Nothing internal is published.
 */
class OfficeDirectoryResource extends WebsiteContentResource
{
    protected static ?string $model = Department::class;

    protected static ?string $slug = 'office-directory';

    protected static ?string $pluralModelLabel = 'Office directory';

    protected static ?string $modelLabel = 'office';

    protected static ?string $navigationLabel = 'Office directory';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?int $navigationSort = 9;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_active', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                LanguageTabs::make(fn (string $locale, bool $required): array => [
                    TextInput::make("public_name.{$locale}")
                        ->label(__('Name on the website'))
                        ->placeholder(fn (?Department $record): ?string => $record?->name)
                        ->maxLength(160)
                        ->helperText($required ? __('Leave empty to use the office name from the admin.') : null),
                    Textarea::make("public_description.{$locale}")
                        ->label(__('What people can come here for'))
                        ->rows(3)
                        ->maxLength(400),
                    TextInput::make("public_location.{$locale}")
                        ->label(__('Where to find it'))
                        ->placeholder(__('For example: Main administration building, first floor'))
                        ->maxLength(160),
                ]),
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('public_phone')->label(__('Public phone'))->tel()->maxLength(40),
                    TextInput::make('public_email')->label(__('Public email'))->email()->maxLength(160),
                ]),
                Toggle::make('is_public')
                    ->label(__('Show on the website'))
                    ->helperText(__('Only the details on this form are shown. Staff names and reports are never published.')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Office'))
                    ->description(fn (Department $record): ?string => filled($record->public_name['om'] ?? null) ? $record->public_name['om'] : null)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('public_phone')->label(__('Public phone'))->placeholder('—')->visibleFrom('md'),
                TextColumn::make('public_email')->label(__('Public email'))->placeholder('—')->visibleFrom('lg'),
                ToggleColumn::make('is_public')->label(__('On website')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit')),
            ])
            ->emptyStateHeading(__('No offices yet'))
            ->emptyStateDescription(__('Offices are set up under Organization. They then appear here to publish.'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageOfficeDirectory::route('/')];
    }
}
