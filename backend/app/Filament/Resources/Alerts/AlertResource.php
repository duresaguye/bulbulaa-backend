<?php

namespace App\Filament\Resources\Alerts;

use App\Enums\AlertLevel;
use App\Filament\Resources\Alerts\Pages\ManageAlerts;
use App\Filament\Support\LanguageTabs;
use App\Filament\Support\WebsiteContentResource;
use App\Models\Alert;
use App\Services\EthiopianCalendar;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * Short messages at the top of every website page: water interruptions, road closures, safety warnings.
 */
class AlertResource extends WebsiteContentResource
{
    protected static ?string $model = Alert::class;

    protected static ?string $pluralModelLabel = 'Alerts';

    protected static ?string $modelLabel = 'alert';

    protected static ?string $navigationLabel = 'Alerts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 6;

    public static function getNavigationBadge(): ?string
    {
        $showing = Alert::query()->current()->count();

        return $showing > 0 ? (string) $showing : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Alerts showing on the website now');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                LanguageTabs::make(fn (string $locale, bool $required): array => [
                    Textarea::make("message.{$locale}")
                        ->label(__('Message'))
                        ->required($required)
                        ->rows(2)
                        ->maxLength(200)
                        ->helperText(__('One short sentence: what is happening, where, and until when.')),
                    TextInput::make("link_label.{$locale}")
                        ->label(__('Link text (optional)'))
                        ->placeholder(__('For example: Read more'))
                        ->maxLength(40),
                ]),
                ToggleButtons::make('level')
                    ->label(__('Importance'))
                    ->options(AlertLevel::class)
                    ->default(AlertLevel::Info)
                    ->inline()
                    ->required(),
                TextInput::make('link_url')
                    ->label(__('Link (optional)'))
                    ->url()
                    ->maxLength(255)
                    ->helperText(__('For example the full announcement on the website.')),
                Grid::make(2)->schema([
                    DateTimePicker::make('starts_at')
                        ->label(__('Show from'))
                        ->seconds(false)
                        ->helperText(__('Empty: straight away.')),
                    DateTimePicker::make('ends_at')
                        ->label(__('Show until'))
                        ->seconds(false)
                        ->after('starts_at')
                        ->helperText(__('Empty: until switched off.')),
                ]),
                Toggle::make('is_active')->label(__('Switched on'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('message')
                    ->label(__('Message'))
                    ->state(fn (Alert $record): string => (string) $record->translated('message', app()->getLocale()))
                    ->wrap(),
                TextColumn::make('level')->label(__('Importance'))->badge()->visibleFrom('md'),
                TextColumn::make('showing')
                    ->label(__('On website'))
                    ->badge()
                    ->state(fn (Alert $record): string => match (true) {
                        $record->isShowing() => __('Showing'),
                        ! $record->is_active => __('Switched off'),
                        $record->starts_at?->isFuture() ?? false => __('Scheduled'),
                        default => __('Ended'),
                    })
                    ->color(fn (Alert $record): string => $record->isShowing() ? 'success' : 'gray'),
                TextColumn::make('ends_at')
                    ->label(__('Show until'))
                    ->formatStateUsing(fn ($state): string => EthiopianCalendar::display($state, withTime: true))
                    ->placeholder('—')
                    ->visibleFrom('lg'),
                ToggleColumn::make('is_active')->label(__('Switched on'))->visibleFrom('sm'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit')),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ])
            ->emptyStateHeading(__('No alerts'))
            ->emptyStateDescription(__('Add an alert to show a short message at the top of every website page, for example a water interruption.'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageAlerts::route('/')];
    }
}
