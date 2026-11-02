<?php

namespace App\Filament\Resources\Documents;

use App\Enums\DocumentCategory;
use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Filament\Support\LanguageTabs;
use App\Filament\Support\ReportTableParts;
use App\Filament\Support\WebsiteContentResource;
use App\Models\Document;
use App\Services\EthiopianCalendar;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

/**
 * Public documents people can download from the website: forms, proclamations, reports and guidelines.
 */
class DocumentResource extends WebsiteContentResource
{
    protected static ?string $model = Document::class;

    protected static ?string $pluralModelLabel = 'Documents';

    protected static ?string $modelLabel = 'document';

    protected static ?string $navigationLabel = 'Documents';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                LanguageTabs::make(fn (string $locale, bool $required): array => [
                    TextInput::make("title.{$locale}")->label(__('Title'))->required($required)->maxLength(200),
                    Textarea::make("description.{$locale}")->label(__('Short description'))->rows(2)->maxLength(300),
                ]),
                Select::make('category')
                    ->label(__('Category'))
                    ->options(DocumentCategory::class)
                    ->default(DocumentCategory::Form)
                    ->required(),
                FileUpload::make('file')
                    ->label(__('File'))
                    ->required()
                    ->disk(Document::DISK)
                    ->directory('documents')
                    ->visibility('public')
                    ->storeFileNamesIn('file_name')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(20480)
                    ->helperText(__('PDF, Word or Excel, up to 20 MB. Anyone can download it, so never upload personal or internal files.')),
                DateTimePicker::make('published_at')
                    ->label(__('Publish date'))
                    ->seconds(false)
                    ->default(now())
                    ->helperText(__('Leave empty to keep it hidden. A future date publishes it automatically then.')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->state(fn (Document $record): string => (string) $record->translated('title', app()->getLocale()))
                    ->description(fn (Document $record): ?HtmlString => ReportTableParts::phoneDetail($record->category->getLabel()))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('title', 'like', "%{$search}%"))
                    ->wrap(),
                TextColumn::make('category')->label(__('Category'))->badge()->visibleFrom('md'),
                TextColumn::make('file_info')
                    ->label(__('File'))
                    ->state(fn (Document $record): string => trim($record->fileType().' '.($record->file_size ? Number::fileSize($record->file_size) : '')))
                    ->visibleFrom('lg'),
                TextColumn::make('published_at')
                    ->label(__('Publish date'))
                    ->formatStateUsing(fn ($state): string => EthiopianCalendar::display($state))
                    ->placeholder(__('Hidden'))
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('category')->label(__('Category'))->options(DocumentCategory::class),
            ])
            ->recordActions([
                Action::make('download')
                    ->iconButton()
                    ->tooltip(__('Download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (Document $record): string => Storage::disk(Document::DISK)->url($record->file), shouldOpenInNewTab: true),
                EditAction::make()->iconButton()->tooltip(__('Edit')),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ])
            ->emptyStateHeading(__('No documents yet'))
            ->emptyStateDescription(__('Upload forms, proclamations, reports and guidelines for people to download from the website.'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageDocuments::route('/')];
    }
}
