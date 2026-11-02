<?php

namespace App\Filament\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\LanguageTabs;
use App\Models\SiteSetting;
use App\Support\Locales;
use App\Support\WebsiteDefaults;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Website text that is not a list of items: the mayor and his message, contact details and social links, the home
 * page cover, the city facts, the industrial park figures and the About page photos. Opens with what the website
 * shows today, so staff edit rather than retype.
 *
 * @property-read Schema $form
 */
class SiteSettings extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Site settings';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament-panels::pages.page';

    /** Paths of uploaded photos, so photos replaced or removed can be deleted from storage. */
    private const PHOTO_PATHS = ['mayor.photo', 'home.hero_image'];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('cms.manage') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(collect(SiteSetting::SECTIONS)
            ->mapWithKeys(fn (string $key): array => [$key => SiteSetting::section($key) ?? WebsiteDefaults::section($key)])
            ->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make(__('Site settings'))
                    ->persistTabInQueryString()
                    ->tabs([
                        $this->mayorTab(),
                        $this->contactTab(),
                        $this->homeTab(),
                        $this->cityTab(),
                        $this->investmentTab(),
                        $this->aboutTab(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('Save changes'))->submit('save')->keyBindings(['mod+s']),
                    ])->sticky(),
                ]),
        ]);
    }

    public function save(): void
    {
        $before = collect(SiteSetting::SECTIONS)->mapWithKeys(fn (string $key): array => [$key => SiteSetting::section($key) ?? []])->all();
        $state = $this->form->getState();

        foreach (SiteSetting::SECTIONS as $key) {
            SiteSetting::store($key, $state[$key] ?? []);
        }

        $this->deleteReplacedPhotos($before, $state);

        Notification::make()
            ->success()
            ->title(__('Saved'))
            ->body(__('The website shows the changes within a few minutes.'))
            ->send();
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function deleteReplacedPhotos(array $before, array $after): void
    {
        $photos = fn (array $state): array => array_filter([
            ...array_map(fn (string $path): mixed => Arr::get($state, $path), self::PHOTO_PATHS),
            ...array_column(Arr::get($state, 'about.gallery', []) ?? [], 'image'),
        ], 'is_string');

        $removed = array_diff($photos($before), $photos($after));

        if ($removed !== []) {
            Storage::disk('public')->delete(array_values($removed));
        }
    }

    private function mayorTab(): Tab
    {
        return Tab::make(__('Mayor'))
            ->icon(Heroicon::OutlinedUser)
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])->schema([
                    FileUpload::make('mayor.photo')
                        ->label(__('Photo'))
                        ->image()
                        ->imageEditor()
                        ->imageCropAspectRatio('4:5')
                        ->disk('public')
                        ->directory('site')
                        ->visibility('public')
                        ->maxSize(5120)
                        ->helperText(__('A portrait photo. Leave empty to keep the current photo on the website.')),
                    Grid::make(1)->columnSpan(['lg' => 2])->schema([
                        TextInput::make('mayor.name')->label(__('Name'))->required()->maxLength(120),
                        LanguageTabs::make(fn (string $locale, bool $required): array => [
                            TextInput::make("mayor.title.{$locale}")->label(__('Title'))->required($required)->maxLength(160),
                            TextInput::make("mayor.quote.{$locale}")
                                ->label(__('Short quote'))
                                ->required($required)
                                ->maxLength(160)
                                ->helperText(__('Shown large on the home page.')),
                            Textarea::make("mayor.excerpt.{$locale}")
                                ->label(__('Short message'))
                                ->rows(3)
                                ->maxLength(400)
                                ->helperText(__('Two or three sentences under the quote on the home page.')),
                            Textarea::make("mayor.message.{$locale}")
                                ->label(__('Full message'))
                                ->rows(8)
                                ->maxLength(6000)
                                ->helperText(__('Shown on the About page. Leave an empty line between paragraphs.')),
                        ]),
                    ]),
                ]),
            ]);
    }

    private function contactTab(): Tab
    {
        return Tab::make(__('Contact'))
            ->icon(Heroicon::OutlinedPhone)
            ->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('contact.phone')->label(__('Phone'))->tel()->maxLength(40),
                    TextInput::make('contact.email')->label(__('Email'))->email()->maxLength(160),
                ]),
                LanguageTabs::make(fn (string $locale, bool $required): array => [
                    TextInput::make("contact.address.{$locale}")->label(__('Address'))->maxLength(200),
                ]),
                Section::make(__('Social media'))
                    ->description(__('Full addresses of the city\'s official pages. Leave empty to hide.'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('contact.facebook')->label('Facebook')->url()->maxLength(255),
                        TextInput::make('contact.telegram')->label('Telegram')->url()->maxLength(255),
                        TextInput::make('contact.youtube')->label('YouTube')->url()->maxLength(255),
                        TextInput::make('contact.x')->label('X (Twitter)')->url()->maxLength(255),
                    ]),
            ]);
    }

    private function homeTab(): Tab
    {
        return Tab::make(__('Home page'))
            ->icon(Heroicon::OutlinedHome)
            ->schema([
                FileUpload::make('home.hero_image')
                    ->label(__('Cover photo'))
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('site')
                    ->visibility('public')
                    ->maxSize(8192)
                    ->helperText(__('The large photo at the top of the home page. A wide, sharp photo at least 1600 pixels across. Leave empty to keep the current photo.')),
                LanguageTabs::make(fn (string $locale, bool $required): array => [
                    TextInput::make("home.title.{$locale}")->label(__('Welcome headline'))->required($required)->maxLength(90),
                    TextInput::make("home.subtitle.{$locale}")->label(__('Line under the headline'))->maxLength(160),
                    TextInput::make("home.caption.{$locale}")
                        ->label(__('Photo caption'))
                        ->maxLength(120)
                        ->helperText(__('Where the cover photo was taken.')),
                ]),
            ]);
    }

    private function cityTab(): Tab
    {
        return Tab::make(__('City facts'))
            ->icon(Heroicon::OutlinedMapPin)
            ->schema([
                Repeater::make('city.facts')
                    ->label(__('Key facts'))
                    ->helperText(__('Shown as a row of large figures on the home and About pages. Up to five fit on one line.'))
                    ->schema([
                        TextInput::make('value')->label(__('Figure or word'))->required()->maxLength(30),
                        ...$this->languageColumns('label', __('Label'), 80),
                    ])
                    ->columns(['default' => 1, 'md' => 4])
                    ->maxItems(6)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['value'] ?? null),
                Repeater::make('city.points')
                    ->label(__('About the city, point by point'))
                    ->helperText(__('The list beside "What makes Bulbulaa different?" on the home page.'))
                    ->schema($this->languageColumns('text', __('Text'), 300, textarea: true))
                    ->columns(['default' => 1, 'md' => 3])
                    ->maxItems(12)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['text']['om'] ?? null),
            ]);
    }

    private function investmentTab(): Tab
    {
        return Tab::make(__('Industrial park'))
            ->icon(Heroicon::OutlinedBuildingOffice)
            ->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('investment.hectares')->label(__('Size in hectares'))->maxLength(20),
                    TextInput::make('investment.investors')->label(__('Number of investors'))->maxLength(20),
                ]),
                LanguageTabs::make(fn (string $locale, bool $required): array => [
                    TextInput::make("investment.park_name.{$locale}")->label(__('Name of the park'))->required($required)->maxLength(160),
                    TextInput::make("investment.note.{$locale}")
                        ->label(__('Note under the figures'))
                        ->maxLength(200)
                        ->helperText(__('For example where the figures come from. Leave empty once they are confirmed.')),
                ]),
            ]);
    }

    private function aboutTab(): Tab
    {
        return Tab::make(__('About page photos'))
            ->icon(Heroicon::OutlinedPhoto)
            ->schema([
                Repeater::make('about.gallery')
                    ->hiddenLabel()
                    ->helperText(__('Photos at the bottom of the About page. Three in a row look best. Leave empty to keep the current photos.'))
                    ->schema([
                        FileUpload::make('image')
                            ->label(__('Photo'))
                            ->image()
                            ->imageEditor()
                            ->required()
                            ->disk('public')
                            ->directory('site/about')
                            ->visibility('public')
                            ->maxSize(5120),
                        ...$this->languageColumns('caption', __('Caption'), 80),
                    ])
                    ->columns(['default' => 1, 'md' => 4])
                    ->maxItems(9)
                    ->reorderable()
                    ->collapsible(),
            ]);
    }

    /**
     * One field per language side by side, for short texts inside repeaters where tabs would be cramped.
     *
     * @return list<TextInput|Textarea>
     */
    private function languageColumns(string $name, string $label, int $maxLength, bool $textarea = false): array
    {
        return collect(array_keys(Locales::NAMES))->map(function (string $locale) use ($name, $label, $maxLength, $textarea): TextInput|Textarea {
            $field = $textarea ? Textarea::make("{$name}.{$locale}")->rows(3) : TextInput::make("{$name}.{$locale}");

            return $field
                ->label($label.' ('.Locales::NAMES[$locale].')')
                ->required($locale === 'om')
                ->maxLength($maxLength)
                ->extraAttributes(['lang' => $locale]);
        })->all();
    }
}
