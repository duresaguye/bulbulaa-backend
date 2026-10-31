<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostType;
use App\Models\Post;
use App\Support\Locales;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(['default' => 3, 'lg' => 2])
                    ->schema([
                        Tabs::make(__('Language'))
                            ->tabs(collect(Post::LOCALES)->map(fn (string $locale): Tab => self::languageTab($locale))->all()),
                    ]),
                Grid::make(1)
                    ->columnSpan(['default' => 3, 'lg' => 1])
                    ->schema([
                        Section::make(__('Post'))
                            ->schema([
                                Select::make('type')
                                    ->label(__('Type'))
                                    ->options(PostType::class)
                                    ->default(PostType::News)
                                    ->required()
                                    ->live(),
                                DatePicker::make('deadline_on')
                                    ->label(fn (Get $get): string => match (self::type($get)) {
                                        PostType::Vacancy => __('Application deadline'),
                                        PostType::Tender => __('Bid closing date'),
                                        default => __('Valid until'),
                                    })
                                    ->helperText(__('Shown on the website so people know when it closes.'))
                                    ->visible(fn (Get $get): bool => self::type($get)?->usesDeadline() ?? false)
                                    ->required(fn (Get $get): bool => in_array(self::type($get), [PostType::Vacancy, PostType::Tender], true)),
                                Toggle::make('is_featured')
                                    ->label(__('Feature on the home page')),
                            ]),
                        Section::make(__('Publishing'))
                            ->schema([
                                DateTimePicker::make('published_at')
                                    ->label(__('Publish date'))
                                    ->seconds(false)
                                    ->helperText(__('Leave empty to keep it as a draft. A future date publishes it automatically then.')),
                                TextInput::make('slug')
                                    ->label(__('Web address'))
                                    ->prefix('/news/')
                                    ->alphaDash()
                                    ->maxLength(120)
                                    ->unique(ignoreRecord: true)
                                    ->helperText(__('Made from the title if left empty.')),
                            ]),
                        Section::make(__('Pictures and files'))
                            ->schema([
                                FileUpload::make('cover_image')
                                    ->label(__('Cover photo'))
                                    ->image()
                                    ->imageEditor()
                                    ->imageAspectRatio('16:9')
                                    ->disk('public')
                                    ->directory('posts/covers')
                                    ->visibility('public')
                                    ->maxSize(5120)
                                    ->helperText(__('A wide photo works best. Up to 5 MB.')),
                                FileUpload::make('attachment')
                                    ->label(__('Document (PDF)'))
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->disk('public')
                                    ->directory('posts/files')
                                    ->visibility('public')
                                    ->maxSize(10240)
                                    ->helperText(__('For example the full vacancy notice or tender document. Up to 10 MB.')),
                            ]),
                    ]),
            ]);
    }

    private static function type(Get $get): ?PostType
    {
        $type = $get('type');

        return $type instanceof PostType ? $type : PostType::tryFrom((string) $type);
    }

    private static function languageTab(string $locale): Tab
    {
        $required = $locale === Post::DEFAULT_LOCALE;

        return Tab::make(Locales::NAMES[$locale].($required ? ' *' : ''))
            ->schema([
                TextInput::make("title.{$locale}")
                    ->label(__('Title'))
                    ->required($required)
                    ->maxLength(200),
                Textarea::make("excerpt.{$locale}")
                    ->label(__('Summary'))
                    ->rows(3)
                    ->maxLength(400)
                    ->helperText(__('One or two sentences shown in lists and when the post is shared.')),
                RichEditor::make("body.{$locale}")
                    ->label(__('Full text'))
                    ->fileAttachments(false)
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'link'],
                        ['h2', 'h3'],
                        ['bulletList', 'orderedList', 'blockquote'],
                        ['undo', 'redo'],
                    ]),
            ])
            ->extraAttributes(['lang' => $locale]);
    }
}
