<?php

namespace App\Filament\Resources\Projects;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Support\ArticleEditor;
use App\Filament\Support\LanguageTabs;
use App\Filament\Support\ReportTableParts;
use App\Filament\Support\WebsiteContentResource;
use App\Models\Project;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * City projects for the website's projects page, each with a photo gallery.
 */
class ProjectResource extends WebsiteContentResource
{
    protected static ?string $model = Project::class;

    protected static ?string $pluralModelLabel = 'Projects';

    protected static ?string $modelLabel = 'project';

    protected static ?string $navigationLabel = 'Projects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(['default' => 3, 'lg' => 2])
                    ->schema([
                        LanguageTabs::make(fn (string $locale, bool $required): array => [
                            TextInput::make("title.{$locale}")->label(__('Title'))->required($required)->maxLength(200),
                            TextInput::make("category.{$locale}")
                                ->label(__('Category'))
                                ->placeholder(__('For example: Roads, Water, Industry'))
                                ->maxLength(60),
                            Textarea::make("summary.{$locale}")
                                ->label(__('Summary'))
                                ->rows(3)
                                ->maxLength(400)
                                ->helperText(__('One or two sentences shown in the list of projects.')),
                            ArticleEditor::make("body.{$locale}")->label(__('Full description')),
                        ]),
                        Section::make(__('Photos'))
                            ->schema([
                                FileUpload::make('images')
                                    ->hiddenLabel()
                                    ->image()
                                    ->imageEditor()
                                    ->multiple()
                                    ->reorderable()
                                    ->appendFiles()
                                    ->panelLayout('grid')
                                    ->maxFiles(12)
                                    ->maxSize(5120)
                                    ->disk('public')
                                    ->directory('projects')
                                    ->visibility('public')
                                    ->helperText(__('Up to 12 photos of 5 MB each. Drag to change the order; the first photo is the cover.')),
                            ]),
                    ]),
                Grid::make(1)
                    ->columnSpan(['default' => 3, 'lg' => 1])
                    ->schema([
                        Section::make(__('Project'))
                            ->schema([
                                ToggleButtons::make('status')
                                    ->label(__('Status'))
                                    ->options(ProjectStatus::class)
                                    ->default(ProjectStatus::Ongoing)
                                    ->inline()
                                    ->required(),
                                Toggle::make('is_published')
                                    ->label(__('Show on the website')),
                                TextInput::make('slug')
                                    ->label(__('Web address'))
                                    ->prefix('/projects/')
                                    ->alphaDash()
                                    ->maxLength(120)
                                    ->unique(ignoreRecord: true)
                                    ->helperText(__('Made from the title if left empty.')),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')
                    ->label(__('Photo'))
                    ->state(fn (Project $record): ?string => $record->images[0] ?? null)
                    ->disk('public')
                    ->imageHeight(48)
                    ->square()
                    ->visibleFrom('sm'),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->state(fn (Project $record): string => (string) $record->translated('title', app()->getLocale()))
                    ->description(fn (Project $record): ?HtmlString => ReportTableParts::phoneDetail($record->status->getLabel()))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('title', 'like', "%{$search}%"))
                    ->wrap(),
                TextColumn::make('status')->label(__('Status'))->badge()->visibleFrom('md'),
                TextColumn::make('photos')
                    ->label(__('Photos'))
                    ->state(fn (Project $record): int => count($record->images ?? []))
                    ->visibleFrom('lg'),
                ToggleColumn::make('is_published')->label(__('On website')),
                TextColumn::make('editor.name')->label(__('Last edited by'))->placeholder('—')->visibleFrom('xl'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(ProjectStatus::class),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit')),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ])
            ->emptyStateHeading(__('No projects yet'))
            ->emptyStateDescription(__('Add the city\'s projects with photos. Until you add one, the website shows its built-in list.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
