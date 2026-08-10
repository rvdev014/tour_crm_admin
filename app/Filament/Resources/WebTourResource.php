<?php

namespace App\Filament\Resources;

use App\Enums\WebTourPriceStatus;
use App\Enums\WebTourPriceType;
use App\Enums\WebTourStatus;
use App\Filament\Resources\WebTourResource\Pages;
use App\Filament\Resources\WebTourResource\RelationManagers;
use App\Models\Package;
use App\Models\WebTour;
use App\Services\TourService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WebTourResource extends Resource
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
    protected static ?string $model = WebTour::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Website Management';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return ! auth()->user()->isOperator() && ! auth()->user()->isAccountant();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Web Tour')
                ->tabs([

                    // ── Tab 1: Basic Info ────────────────────────────────
                    Forms\Components\Tabs\Tab::make(__('Basic Info'))
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Section::make(__('Tour Names'))
                                ->description(__('Enter the tour name in both languages.'))
                                ->icon('heroicon-o-language')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\TextInput::make('name_ru')
                                        ->label(__('Name (RU)'))
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('name_en')
                                        ->label(__('Name (EN)'))
                                        ->maxLength(255),
                                ]),

                            Forms\Components\Section::make(__('Status & Type'))
                                ->icon('heroicon-o-adjustments-horizontal')
                                ->columns(3)
                                ->schema([
                                    Forms\Components\Select::make('status')
                                        ->options(WebTourStatus::class)
                                        ->default(WebTourStatus::New)
                                        ->required(),
                                    Forms\Components\Select::make('type')
                                        ->label(__('Price Type'))
                                        ->options(WebTourPriceType::class)
                                        ->default(WebTourPriceType::Default)
                                        ->live()
                                        ->required(),
                                    Forms\Components\Toggle::make('is_popular')
                                        ->label(__('Mark as Popular'))
                                        ->default(false)
                                        ->inline(false),
                                ]),

                            Forms\Components\Section::make(__('Tour Dates'))
                                ->description(__('The advertised departure window for this tour, shown on the website. Not applicable to pax-based (Free) pricing, which has no fixed dates.'))
                                ->icon('heroicon-o-calendar')
                                ->columns(2)
                                ->visible(fn ($get) => $get('type') === null || $get('type') === WebTourPriceType::Default->value)
                                ->schema([
                                    Forms\Components\DatePicker::make('start_date')
                                        ->label(__('Start Date'))
                                        ->required(fn ($get) => $get('type') === null || $get('type') === WebTourPriceType::Default->value),
                                    Forms\Components\DatePicker::make('end_date')
                                        ->label(__('End Date'))
                                        ->minDate(fn ($get) => $get('start_date'))
                                        ->required(fn ($get) => $get('type') === null || $get('type') === WebTourPriceType::Default->value),
                                ]),

                            Forms\Components\Section::make(__('Categories'))
                                ->icon('heroicon-o-tag')
                                ->schema([
                                    Forms\Components\Select::make('categories')
                                        ->relationship('categories', 'name_ru')
                                        ->multiple()
                                        ->preload()
                                        ->searchable()
                                        ->label(__('Tour Categories')),
                                ]),
                        ]),

                    // ── Tab 2: Media & Description ───────────────────────
                    Forms\Components\Tabs\Tab::make(__('Media & Description'))
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\Section::make(__('Cover Photo'))
                                ->description(__('This will be the main thumbnail shown in tour listings.'))
                                ->icon('heroicon-o-camera')
                                ->schema([
                                    Forms\Components\FileUpload::make('photo')
                                        ->label(__('Cover Photo'))
                                        ->image()
                                        ->imagePreviewHeight('200')
                                        ->directory('web-tours')
                                        ->maxSize(5120)
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                                ]),

                            Forms\Components\Section::make(__('Photo Gallery'))
                                ->description(__('Upload additional photos shown in the tour gallery (up to 20 images).'))
                                ->icon('heroicon-o-rectangle-stack')
                                ->schema([
                                    Forms\Components\FileUpload::make('photos')
                                        ->label(__('Gallery Photos'))
                                        ->image()
                                        ->multiple()
                                        ->reorderable()
                                        ->maxFiles(20)
                                        ->directory('web-tours/gallery')
                                        ->maxSize(5120)
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                        ->panelLayout('grid')
                                        ->imagePreviewHeight('120'),
                                ]),

                            Forms\Components\Section::make(__('Descriptions'))
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

                    // ── Tab 3: Itinerary ─────────────────────────────────
                    Forms\Components\Tabs\Tab::make(__('Itinerary'))
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Forms\Components\Section::make(__('Daily Program'))
                                ->description(__('Add each day of the tour with its location, photos and activities.'))
                                ->icon('heroicon-o-list-bullet')
                                ->schema([
                                    Forms\Components\Repeater::make('days')
                                        ->relationship('days')
                                        ->label(__(''))
                                        // ->color('danger') below already gives the delete button
                                        // its red styling (with proper hover/active/disabled states)
                                        // through Filament's own button system. The 'red-delete-
                                        // repeater'/'delete-btn' classes and --c-500/--c-600 inline
                                        // style that used to sit here had no matching CSS anywhere
                                        // in the app — dead, and removed rather than reinstated.
                                        ->deleteAction(
                                            fn (Forms\Components\Actions\Action $action) => $action
                                                ->color('danger')
                                        )
                                        ->itemLabel(function ($get, $uuid) {
                                            $index = array_search($uuid, array_keys($get('days'))) ?? 0;

                                            return 'Day '.($index + 1);
                                        })
                                        ->addActionLabel('+ Add Day')
                                        ->addActionAlignment('end')
                                        ->collapsible()
                                        ->collapsed(false)
                                        ->schema([
                                            Forms\Components\Grid::make(3)->schema([
                                                Forms\Components\TextInput::make('place_name_ru')
                                                    ->label(__('Place Name (RU)'))
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('place_name_en')
                                                    ->label(__('Place Name (EN)'))
                                                    ->maxLength(255),
                                                Forms\Components\Select::make('city_id')
                                                    ->label(__('City'))
                                                    ->native(false)
                                                    ->searchable()
                                                    ->preload()
                                                    ->relationship('city', 'name')
                                                    ->options(fn ($get) => TourService::getCities()),
                                            ]),
                                            Forms\Components\Grid::make(2)->schema([
                                                Forms\Components\FileUpload::make('photo')
                                                    ->label(__('Day Photo'))
                                                    ->image()
                                                    ->imagePreviewHeight('120')
                                                    ->directory('web-tours/days')
                                                    ->maxSize(5120),
                                                Forms\Components\Select::make('facilities')
                                                    ->label(__('Facilities / Activities'))
                                                    ->relationship('facilities', 'name_ru')
                                                    ->multiple()
                                                    ->preload()
                                                    ->searchable(),
                                            ]),
                                            Forms\Components\Grid::make(2)->schema([
                                                Forms\Components\RichEditor::make('description_ru')
                                                    ->label(__('Description (RU)'))
                                                    ->required()
                                                    ->toolbarButtons([
                                                        'bold', 'italic', 'bulletList', 'orderedList', 'undo', 'redo',
                                                    ]),
                                                Forms\Components\RichEditor::make('description_en')
                                                    ->label(__('Description (EN)'))
                                                    ->toolbarButtons([
                                                        'bold', 'italic', 'bulletList', 'orderedList', 'undo', 'redo',
                                                    ]),
                                            ]),
                                        ]),
                                ]),
                        ]),

                    // ── Tab 4: Packages & Accommodation ──────────────────
                    Forms\Components\Tabs\Tab::make(__('Packages & Accommodation'))
                        ->icon('heroicon-o-check-badge')
                        ->schema([
                            Forms\Components\Section::make(__('What\'s Included'))
                                ->icon('heroicon-o-check-circle')
                                ->schema([
                                    Forms\Components\Select::make('packagesIncluded')
                                        ->label(__('Included in Price'))
                                        ->relationship('packagesIncluded', 'name_ru')
                                        ->multiple()
                                        ->preload()
                                        ->searchable()
                                        ->reactive()
                                        ->pivotData(['is_include' => true])
                                        ->options(function ($state, $get) {
                                            return Package::query()
                                                ->whereNotIn('id', $get('packagesNotIncluded') ?: [])
                                                ->pluck('name_ru', 'id');
                                        }),
                                ]),

                            Forms\Components\Section::make(__('What\'s Not Included'))
                                ->icon('heroicon-o-x-circle')
                                ->schema([
                                    Forms\Components\Select::make('packagesNotIncluded')
                                        ->label(__('Not Included in Price'))
                                        ->relationship('packagesNotIncluded', 'name_ru')
                                        ->multiple()
                                        ->preload()
                                        ->searchable()
                                        ->reactive()
                                        ->pivotData(['is_include' => false])
                                        ->options(function ($state, $get) {
                                            return Package::query()
                                                ->whereNotIn('id', $get('packagesIncluded') ?: [])
                                                ->pluck('name_ru', 'id');
                                        }),
                                ]),

                            Forms\Components\Section::make(__('Accommodation'))
                                ->description(__('Add hotel accommodation options for this tour.'))
                                ->icon('heroicon-o-building-office')
                                ->schema([
                                    Forms\Components\Repeater::make('accommodations')
                                        ->relationship('accommodations')
                                        ->label(__(''))
                                        ->minItems(0)
                                        ->addActionLabel('+ Add Accommodation')
                                        ->addActionAlignment('end')
                                        ->collapsible()
                                        ->schema([
                                            Forms\Components\Grid::make(2)->schema([
                                                Forms\Components\TextInput::make('header_ru')
                                                    ->label(__('Header (RU)'))
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('header_en')
                                                    ->label(__('Header (EN)'))
                                                    ->maxLength(255),
                                            ]),
                                            Forms\Components\Grid::make(2)->schema([
                                                Forms\Components\RichEditor::make('description_ru')
                                                    ->label(__('Description (RU)'))
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'undo', 'redo']),
                                                Forms\Components\RichEditor::make('description_en')
                                                    ->label(__('Description (EN)'))
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'undo', 'redo']),
                                            ]),
                                            Forms\Components\Select::make('hotels')
                                                ->label(__('Hotels'))
                                                ->relationship('hotels', 'name')
                                                ->multiple()
                                                ->preload()
                                                ->searchable(),
                                        ]),
                                ]),
                        ]),

                    // ── Tab 5: Pricing ────────────────────────────────────
                    Forms\Components\Tabs\Tab::make(__('Pricing'))
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            // Default date-based prices
                            Forms\Components\Section::make(__('Date & Price Entries'))
                                ->description(__('Set price per person (USD) for specific date ranges.'))
                                ->icon('heroicon-o-calendar')
                                ->visible(fn ($get) => $get('type') === null || $get('type') === WebTourPriceType::Default->value)
                                ->schema([
                                    Forms\Components\Repeater::make('prices')
                                        ->relationship('prices')
                                        ->label(__(''))
                                        ->addActionLabel('+ Add Date Range')
                                        ->addActionAlignment('end')
                                        ->collapsible()
                                        ->itemLabel(function ($get, $uuid) {
                                            $item = $get("prices.$uuid") ?? [];
                                            $from = $item['from_date'] ?? '—';
                                            $to = $item['to_date'] ?? '—';
                                            $price = $item['price'] ?? '';

                                            return "$from → $to".($price ? "  |  \${$price}" : '');
                                        })
                                        ->schema([
                                            Forms\Components\Grid::make(3)->schema([
                                                Forms\Components\DatePicker::make('from_date')
                                                    ->label(__('From'))
                                                    ->required(),
                                                Forms\Components\DatePicker::make('to_date')
                                                    ->label(__('To'))
                                                    ->required(),
                                                Forms\Components\DateTimePicker::make('deadline')
                                                    ->label(__('Booking Deadline')),
                                            ]),
                                            Forms\Components\Grid::make(2)->schema([
                                                Forms\Components\TextInput::make('price')
                                                    ->label(__('Price per Person (USD)'))
                                                    ->required()
                                                    ->numeric()
                                                    ->prefix('$'),
                                                Forms\Components\Select::make('status')
                                                    ->label(__('Availability'))
                                                    ->options(WebTourPriceStatus::class),
                                            ]),
                                        ]),
                                ]),

                            // Free / pax-based prices
                            Forms\Components\Section::make(__('Pax-Based Pricing'))
                                ->description(__('Set price per person (USD) depending on group size.'))
                                ->icon('heroicon-o-users')
                                ->visible(fn ($get) => $get('type') === WebTourPriceType::Free->value)
                                ->schema([
                                    Forms\Components\Repeater::make('freePrices')
                                        ->relationship('freePrices')
                                        ->label(__(''))
                                        ->addActionLabel('+ Add Price Tier')
                                        ->addActionAlignment('end')
                                        ->collapsible()
                                        ->itemLabel(function ($get, $uuid) {
                                            $item = $get("freePrices.$uuid") ?? [];
                                            $from = $item['pax_from'] ?? '—';
                                            $to = $item['pax_to'] ?? '—';
                                            $price = $item['price'] ?? '';

                                            return "Pax {$from}–{$to}".($price ? "  |  \${$price}/person" : '');
                                        })
                                        ->schema([
                                            Forms\Components\Grid::make(3)->schema([
                                                Forms\Components\TextInput::make('pax_from')
                                                    ->label(__('Min Pax'))
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(1),
                                                Forms\Components\TextInput::make('pax_to')
                                                    ->label(__('Max Pax'))
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(1),
                                                Forms\Components\TextInput::make('price')
                                                    ->label(__('Price per Person (USD)'))
                                                    ->required()
                                                    ->numeric()
                                                    ->prefix('$'),
                                            ]),
                                        ]),
                                ]),

                            // Per-person exact-count prices
                            Forms\Components\Section::make(__('Per-Person Pricing'))
                                ->description(__('Set price per person (USD) for an exact number of people (e.g. 1 person, 2 people, ...).'))
                                ->icon('heroicon-o-user')
                                ->visible(fn ($get) => $get('type') === WebTourPriceType::PerPerson->value)
                                ->schema([
                                    Forms\Components\Repeater::make('perPersonPrices')
                                        ->relationship('perPersonPrices')
                                        ->label(__(''))
                                        ->addActionLabel('+ Add Price Row')
                                        ->addActionAlignment('end')
                                        ->collapsible()
                                        ->itemLabel(function ($get, $uuid) {
                                            $item = $get("perPersonPrices.$uuid") ?? [];
                                            $pax = $item['pax_count'] ?? '—';
                                            $price = $item['price'] ?? '';

                                            return "{$pax} pax".($price ? "  |  \${$price}/person" : '');
                                        })
                                        ->schema([
                                            Forms\Components\Grid::make(2)->schema([
                                                Forms\Components\TextInput::make('pax_count')
                                                    ->label(__('Number of People'))
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(1),
                                                Forms\Components\TextInput::make('price')
                                                    ->label(__('Price per Person (USD)'))
                                                    ->required()
                                                    ->numeric()
                                                    ->prefix('$'),
                                            ]),
                                        ]),
                                ]),
                        ]),

                    // ── Tab 6: Similar Tours ──────────────────────────────
                    Forms\Components\Tabs\Tab::make(__('Similar Tours'))
                        ->icon('heroicon-o-arrows-right-left')
                        ->schema([
                            Forms\Components\Section::make(__('Related Tours'))
                                ->description(__('Select tours to show in the "More similar tours" section on this tour\'s page.'))
                                ->icon('heroicon-o-link')
                                ->schema([
                                    Forms\Components\Select::make('similarTours')
                                        ->label(__('Similar Tours'))
                                        ->relationship('similarTours', 'name_ru')
                                        ->multiple()
                                        ->preload()
                                        ->searchable()
                                        ->options(function ($state, $get, $record) {
                                            $ignoreIds = $get('similarTours') ?: [];
                                            if ($record) {
                                                $ignoreIds[] = $record->id;
                                            }

                                            return WebTour::query()
                                                ->whereNotIn('id', $ignoreIds)
                                                ->pluck('name_ru', 'id');
                                        }),
                                ]),
                        ]),

                ])->columnSpanFull()->persistTabInQueryString(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->columns([
                Tables\Columns\ImageColumn::make('photo')
                    ->height('52px')
                    ->width('80px')
                    ->defaultImageUrl(asset('images/placeholder.png')),

                Tables\Columns\TextColumn::make('name_ru')
                    ->label(__('Name (RU)'))
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('name_en')
                    ->label(__('Name (EN)'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('Start'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label(__('End'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('prices')
                    ->label(__('Prices'))
                    ->formatStateUsing(fn () => 'View prices')
                    ->html()
                    ->action(WebTourResource\Actions\PricesAction::make()),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_popular')
                    ->label(__('Popular'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ], position: Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn () => auth()->user()->isAdmin()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebTours::route('/'),
            'create' => Pages\CreateWebTour::route('/create'),
            'edit' => Pages\EditWebTour::route('/{record}/edit'),
        ];
    }
}
