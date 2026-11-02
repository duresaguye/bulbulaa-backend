<?php

namespace App\Filament\Resources\InvestmentSectors;

use App\Filament\Resources\InvestmentSectors\Pages\ManageInvestmentSectors;
use App\Filament\Support\LanguageTabs;
use App\Filament\Support\WebsiteContentResource;
use App\Models\InvestmentSector;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * The investment page's sectors: what each offers and what can be invested in.
 */
class InvestmentSectorResource extends WebsiteContentResource
{
    protected static ?string $model = InvestmentSector::class;

    protected static ?string $pluralModelLabel = 'Investment sectors';

    protected static ?string $modelLabel = 'investment sector';

    protected static ?string $navigationLabel = 'Investment';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                LanguageTabs::make(fn (string $locale, bool $required): array => [
                    TextInput::make("title.{$locale}")->label(__('Name'))->required($required)->maxLength(80),
                    Textarea::make("description.{$locale}")->label(__('Description'))->rows(3)->maxLength(400),
                    TagsInput::make("items.{$locale}")
                        ->label(__('What can be invested in'))
                        ->placeholder(__('Type and press Enter'))
                        ->reorderable(),
                ]),
                Select::make('kind')
                    ->label(__('Kind'))
                    ->options(collect(InvestmentSector::KINDS)->map(fn (string $label): string => __($label))->all())
                    ->default('other')
                    ->required()
                    ->helperText(__('The tourism sector is also shown on the tourism page.')),
                FileUpload::make('image')
                    ->label(__('Photo'))
                    ->image()
                    ->imageEditor()
                    ->maxSize(5120)
                    ->disk('public')
                    ->directory('investment')
                    ->visibility('public'),
                Toggle::make('is_published')->label(__('Show on the website')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label(__('Photo'))->disk('public')->imageHeight(48)->square()->visibleFrom('sm'),
                TextColumn::make('title')
                    ->label(__('Name'))
                    ->state(fn (InvestmentSector $record): string => (string) $record->translated('title', app()->getLocale()))
                    ->description(fn (InvestmentSector $record): string => implode(', ', array_slice($record->itemsIn(app()->getLocale()), 0, 4)))
                    ->wrap(),
                ToggleColumn::make('is_published')->label(__('On website')),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit')),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ])
            ->emptyStateHeading(__('No investment sectors yet'))
            ->emptyStateDescription(__('Until you add one, the website shows its built-in list. Use "Start from the current website text" to edit that list here.'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageInvestmentSectors::route('/')];
    }
}
