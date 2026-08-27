<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DestinationResource\Pages;
use App\Models\Destination;
use App\Models\WebTour;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DestinationResource extends Resource
{
    // Sidebar label — Filament otherwise falls back to the auto-derived
    // English plural model name (e.g. "Hotels"), which never changes with
    // the panel's locale. See AppServiceProvider for the equivalent
    // ->translateLabel() hook covering field/column labels; this can't be
    // done the same way since getNavigationLabel() is called statically.
    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }

    // See the comment on getNavigationLabel() above / AdminPanelProvider's
    // navigationGroups() — Filament matches resources to their registered
    // group by comparing this value against the group's getLabel(), so both
    // sides need translating the same way or the match silently fails.
    public static function getNavigationGroup(): ?string
    {
        return ($group = parent::getNavigationGroup()) ? __($group) : null;
    }

    // Breadcrumb text ("X > List" above the page heading) — a third, separate
    // label pipeline from getNavigationLabel()/getNavigationGroup() above
    // (falls back to getTitleCasePluralModelLabel(), not either of those).
    public static function getBreadcrumb(): string
    {
        return __(parent::getBreadcrumb());
    }

    // Plural model label — feeds table empty states ("Не найдено tours") and
    // some page headings. Singular getModelLabel() is deliberately NOT
    // overridden; see the class-level comment above the other nav overrides.
    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }

    protected static ?string $model = Destination::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static ?string $navigationGroup = 'Website Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title_ru';

    public static function canViewAny(): bool
    {
        return ! auth()->user()->isOperator() && ! auth()->user()->isAccountant();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Destination')
                ->tabs([

                    // ── Tab 1: Basic Info ────────────────────────────────
                    Forms\Components\Tabs\Tab::make(__('Basic Info'))
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Section::make(__('Hierarchy'))
                                ->description(__('Leave "Country" empty for a country-level destination. Set it to nest this as a place inside that country.'))
                                ->icon('heroicon-o-map')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\Select::make('parent_id')
                                        ->label(__('Country'))
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->placeholder(__('— country level —'))
                                        ->relationship('parent', 'title_ru', fn ($query) => $query->whereNull('parent_id'))
                                        ->live(),
                                    Forms\Components\Select::make('city_id')
                                        ->label(__('City'))
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->relationship('city', 'name')
                                        ->helperText(__('Linking a city pulls in every tour whose itinerary visits it.'))
                                        ->visible(fn ($get) => filled($get('parent_id'))),
                                ]),

                            Forms\Components\Section::make(__('Titles'))
                                ->icon('heroicon-o-language')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\TextInput::make('title_ru')
                                        ->label(__('Title (RU)'))
                                        ->required()
                                        ->live(onBlur: true)
                                        ->maxLength(255)
                                        ->afterStateUpdated(function ($state, $get, $set) {
                                            if (! $get('slug')) {
                                                $set('slug', Str::slug($get('title_en') ?: $state));
                                            }
                                        }),
                                    Forms\Components\TextInput::make('title_en')
                                        ->label(__('Title (EN)'))
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, $get, $set) {
                                            if (! $get('slug')) {
                                                $set('slug', Str::slug($state ?: $get('title_ru')));
                                            }
                                        }),
                                    Forms\Components\TextInput::make('slug')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(ignoreRecord: true)
                                        ->helperText(__('Used in the website URL, e.g. /destinations/kazakhstan.')),
                                ]),

                            Forms\Components\Section::make(__('Publishing'))
                                ->icon('heroicon-o-adjustments-horizontal')
                                ->columns(3)
                                ->schema([
                                    Forms\Components\Toggle::make('is_published')
                                        ->label(__('Published'))
                                        ->default(false)
                                        ->inline(false),
                                    Forms\Components\Toggle::make('is_featured')
                                        ->label(__('Featured'))
                                        ->helperText(__('Shown first in the navigation dropdown.'))
                                        ->default(false)
                                        ->inline(false),
                                    Forms\Components\TextInput::make('order')
                                        ->label(__('Order'))
                                        ->numeric()
                                        ->default(0),
                                ]),
                        ]),

                    // ── Tab 2: Media & Description ───────────────────────
                    Forms\Components\Tabs\Tab::make(__('Media & Description'))
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\Section::make(__('Cover Photo'))
                                ->description(__('This will be the main hero image shown on the destination page.'))
                                ->icon('heroicon-o-camera')
                                ->schema([
                                    Forms\Components\FileUpload::make('photo')
                                        ->label(__('Cover Photo'))
                                        ->image()
                                        ->imagePreviewHeight('200')
                                        ->directory('destinations')
                                        ->maxSize(5120)
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                                ]),

                            Forms\Components\Section::make(__('Photo Gallery'))
                                ->description(__('Upload additional photos shown in the destination gallery (up to 20 images).'))
                                ->icon('heroicon-o-rectangle-stack')
                                ->schema([
                                    Forms\Components\FileUpload::make('photos')
                                        ->label(__('Gallery Photos'))
                                        ->image()
                                        ->multiple()
                                        ->reorderable()
                                        ->maxFiles(20)
                                        ->directory('destinations/gallery')
                                        ->maxSize(5120)
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                        ->panelLayout('grid')
                                        ->imagePreviewHeight('120'),
                                ]),

                            Forms\Components\Section::make(__('Short Description'))
                                ->description(__('A one or two sentence blurb shown on cards and in the navigation dropdown.'))
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\Textarea::make('short_description_ru')
                                        ->label(__('Short Description (RU)'))
                                        ->rows(3),
                                    Forms\Components\Textarea::make('short_description_en')
                                        ->label(__('Short Description (EN)'))
                                        ->rows(3),
                                ]),

                            Forms\Components\Section::make(__('Description'))
                                ->icon('heroicon-o-document-text')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\RichEditor::make('description_ru')
                                        ->label(__('Description (RU)'))
                                        ->toolbarButtons([
                                            'bold', 'italic', 'underline', 'strike',
                                            'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                        ]),
                                    Forms\Components\RichEditor::make('description_en')
                                        ->label(__('Description (EN)'))
                                        ->toolbarButtons([
                                            'bold', 'italic', 'underline', 'strike',
                                            'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                        ]),
                                ]),
                        ]),

                    // ── Tab 3: Sections ──────────────────────────────────
                    Forms\Components\Tabs\Tab::make(__('Sections'))
                        ->icon('heroicon-o-list-bullet')
                        ->schema([
                            Forms\Components\Section::make(__('Article Sections'))
                                ->description(__('Anchor-linked content blocks such as History, Things to do, Museums, Food, Transport, Safety…'))
                                ->icon('heroicon-o-bars-3-bottom-left')
                                ->schema([
                                    Forms\Components\Repeater::make('sections')
                                        ->relationship('sections')
                                        ->label(__(''))
                                        ->orderColumn('order')
                                        ->collapsible()
                                        ->addActionLabel('+ Add Section')
                                        ->addActionAlignment('end')
                                        ->itemLabel(fn ($state) => $state['title_ru'] ?? null)
                                        ->schema([
                                            Forms\Components\Grid::make(3)->schema([
                                                Forms\Components\TextInput::make('title_ru')
                                                    ->label(__('Title (RU)'))
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('title_en')
                                                    ->label(__('Title (EN)'))
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('anchor')
                                                    ->label(__('Anchor'))
                                                    ->maxLength(255)
                                                    ->helperText(__('Used for the in-page jump link, e.g. "history".')),
                                            ]),
                                            Forms\Components\Grid::make(2)->schema([
                                                Forms\Components\RichEditor::make('content_ru')
                                                    ->label(__('Content (RU)'))
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                                                Forms\Components\RichEditor::make('content_en')
                                                    ->label(__('Content (EN)'))
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                                            ]),
                                        ]),
                                ]),
                        ]),

                    // ── Tab 4: Tours ──────────────────────────────────────
                    Forms\Components\Tabs\Tab::make(__('Tours'))
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Forms\Components\Section::make(__('Auto-Matched Tours'))
                                ->description(fn ($record) => $record
                                    ? __(':count tour(s) are shown automatically based on itinerary city.', ['count' => $record->toursQuery()->count()])
                                    : __('Auto-matched tours will be shown here once this destination is saved.'))
                                ->icon('heroicon-o-sparkles'),

                            Forms\Components\Section::make(__('Pinned Tours'))
                                ->description(__('Manually feature extra tours on this destination page, in addition to the auto-matched ones above.'))
                                ->icon('heroicon-o-star')
                                ->schema([
                                    Forms\Components\Select::make('pinnedTours')
                                        ->label(__('Pinned Tours'))
                                        ->relationship('pinnedTours', 'name_ru')
                                        ->multiple()
                                        ->preload()
                                        ->searchable()
                                        ->options(fn () => WebTour::query()->pluck('name_ru', 'id')),
                                ]),
                        ]),

                    // ── Tab 5: SEO ────────────────────────────────────────
                    Forms\Components\Tabs\Tab::make(__('SEO'))
                        ->icon('heroicon-o-magnifying-glass')
                        ->schema([
                            Forms\Components\Section::make(__('Search Engine Metadata'))
                                ->icon('heroicon-o-code-bracket')
                                ->schema([
                                    Forms\Components\TextInput::make('seo_title')
                                        ->label(__('SEO Title'))
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('seo_description')
                                        ->label(__('SEO Description'))
                                        ->rows(3),
                                ]),
                        ]),

                ])->columnSpanFull()->persistTabInQueryString(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->striped()
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->columns([
                Tables\Columns\ImageColumn::make('photo')
                    ->height('52px')
                    ->width('80px'),

                Tables\Columns\TextColumn::make('title_ru')
                    ->label(__('Title'))
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('parent.title_ru')
                    ->label(__('Country'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('city.name')
                    ->label(__('City'))
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_published')
                    ->label(__('Published'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label(__('Featured'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('order')
                    ->label(__('Order'))
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label(__('Published')),
                SelectFilter::make('parent_id')
                    ->label(__('Country'))
                    ->options(fn () => Destination::query()->countries()->pluck('title_ru', 'id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->authorize(fn () => auth()->user()->isAdmin()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn () => auth()->user()->isAdmin()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDestinations::route('/'),
            'create' => Pages\CreateDestination::route('/create'),
            'edit' => Pages\EditDestination::route('/{record}/edit'),
        ];
    }
}
