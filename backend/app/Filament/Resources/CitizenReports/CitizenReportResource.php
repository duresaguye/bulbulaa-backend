<?php

namespace App\Filament\Resources\CitizenReports;

use App\Enums\CitizenReportStatus;
use App\Enums\ReportCategory;
use App\Filament\Resources\CitizenReports\Pages\ListCitizenReports;
use App\Filament\Resources\CitizenReports\Pages\ViewCitizenReport;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\ReportTableParts;
use App\Models\CitizenReport;
use App\Services\EthiopianCalendar;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The inbox for messages, complaints, safety concerns and suggestions sent from the website.
 */
class CitizenReportResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = CitizenReport::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $pluralModelLabel = 'Messages from the public';

    protected static ?string $modelLabel = 'message';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Messages from the public';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('citizen_reports.manage') ?? false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny()) {
            return null;
        }

        $new = CitizenReport::query()->where('status', CitizenReportStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('details')
                    ->label(__('Message'))
                    ->formatStateUsing(fn (string $state): string => Str::limit($state, 90))
                    ->description(fn (CitizenReport $record): HtmlString => new HtmlString(
                        '<span>'.e($record->reference.($record->location ? ', '.$record->location : '')).'</span>'
                        .(ReportTableParts::phoneDetail(', '.$record->category->getLabel().', '.$record->status->getLabel()) ?? '')
                    ))
                    ->searchable(['details', 'reference', 'location'])
                    ->wrap(),
                TextColumn::make('category')
                    ->label(__('Type'))
                    ->badge()
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->visibleFrom('md'),
                IconColumn::make('has_photos')
                    ->label(__('Photos'))
                    ->state(fn (CitizenReport $record): ?bool => filled($record->photos) ?: null)
                    ->icon(Heroicon::OutlinedPhoto)
                    ->visibleFrom('xl'),
                TextColumn::make('department.name')
                    ->label(__('Office'))
                    ->placeholder('—')
                    ->visibleFrom('2xl'),
                TextColumn::make('created_at')
                    ->label(__('Received'))
                    ->formatStateUsing(function ($state): string {
                        $date = EthiopianCalendar::fromGregorian($state);

                        return EthiopianCalendar::format($date['year'], $date['month'], $date['day']);
                    })
                    ->tooltip(fn (CitizenReport $record): string => EthiopianCalendar::display($record->created_at, withTime: true))
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(CitizenReportStatus::class),
                SelectFilter::make('category')->label(__('Type'))->options(ReportCategory::class),
                SelectFilter::make('department_id')->label(__('Office'))->relationship('department', 'name'),
            ])
            ->recordActions([ViewAction::make()->iconButton()->tooltip(__('Open'))])
            ->emptyStateIcon(Heroicon::OutlinedInboxArrowDown)
            ->emptyStateHeading(__('No messages yet'))
            ->emptyStateDescription(__('Messages, complaints and reports sent from the website\'s contact page appear here.'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('Message'))
                    ->columnSpan(['default' => 3, 'lg' => 2])
                    ->schema([
                        TextEntry::make('details')->hiddenLabel()->prose()->extraAttributes(['class' => 'rt-preserve-lines']),
                        TextEntry::make('location')->label(__('Location'))->placeholder(__('Not given'))->icon(Heroicon::OutlinedMapPin),
                        ViewEntry::make('photos')
                            ->label(__('Photos'))
                            ->view('filament.citizen-reports.photos')
                            ->visible(fn (CitizenReport $record): bool => filled($record->photos)),
                    ]),
                Section::make(__('Details'))
                    ->columnSpan(['default' => 3, 'lg' => 1])
                    ->schema([
                        TextEntry::make('reference')->label(__('Reference'))->copyable()->weight('bold'),
                        TextEntry::make('category')->label(__('Type'))->badge(),
                        TextEntry::make('status')->label(__('Status'))->badge(),
                        TextEntry::make('created_at')->label(__('Received'))
                            ->formatStateUsing(fn ($state): string => EthiopianCalendar::display($state, withTime: true)),
                        TextEntry::make('reporter')
                            ->label(__('Sent by'))
                            ->state(fn (CitizenReport $record): string => $record->is_anonymous
                                ? __('Anonymous')
                                : collect([$record->reporter_name, $record->reporter_contact])->filter()->join(', ')),
                        TextEntry::make('department.name')->label(__('Office handling it'))->placeholder(__('Not assigned')),
                        TextEntry::make('staff_note')->label(__('Internal note'))->placeholder('—'),
                        TextEntry::make('handler.name')->label(__('Last updated by'))->placeholder('—'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCitizenReports::route('/'),
            'view' => ViewCitizenReport::route('/{record}'),
        ];
    }
}
