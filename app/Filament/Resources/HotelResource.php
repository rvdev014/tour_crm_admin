<?php

namespace App\Filament\Resources;

use App\Enums\CurrencyEnum;
use App\Enums\RateEnum;
use App\Enums\RoomSeasonType;
use App\Filament\Resources\HotelResource\Actions\HotelPeriodsAction;
use App\Filament\Resources\HotelResource\Pages;
use App\Filament\Resources\HotelResource\RelationManagers;
use App\Models\City;
use App\Models\Hotel;
use App\Models\HotelRequest;
use App\Models\TourDayExpense;
use App\Services\TourService;
use App\Tables\Columns\PeriodsColumn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class HotelResource extends Resource
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
    protected static ?string $model = Hotel::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationGroup = 'Manual';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'inn', 'company_name', 'address', 'phones.phone_number'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Email' => $record->email,
            'Address' => $record->address,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->disabled(fn () => auth()->user()->isOperator())
            ->schema([
                Forms\Components\Tabs::make('Hotel')
                    ->tabs([

                        // ── Tab 1: Basic Information ─────────────────────
                        Forms\Components\Tabs\Tab::make(__('Basic Info'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([

                                Forms\Components\Section::make(__('Hotel Identity'))
                                    ->icon('heroicon-o-building-office')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label(__('Hotel Name'))
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true)
                                            ->validationMessages([
                                                'unique' => 'A hotel with this name already exists. Check the hotel list before creating a new one.',
                                            ])
                                            ->columnSpan(2),
                                        Forms\Components\Select::make('rate')
                                            ->label(__('Star Rating'))
                                            ->options(function () {
                                                $options = [];
                                                foreach (RateEnum::cases() as $rate) {
                                                    $options[$rate->value] = $rate->getLabel();
                                                }

                                                return $options;
                                            }),
                                        Forms\Components\TextInput::make('email')
                                            ->label(__('Email'))
                                            ->email()
                                            ->suffixAction(function ($record) {
                                                if (! $record?->email) {
                                                    return [];
                                                }

                                                return [
                                                    Forms\Components\Actions\Action::make('hotel_email')
                                                        ->icon('heroicon-o-paper-airplane')
                                                        ->url("mailto:{$record->email}", true),
                                                ];
                                            }),
                                        Forms\Components\TextInput::make('inn')
                                            ->label(__('INN')),
                                        Forms\Components\Toggle::make('is_visible')
                                            ->label(__('Visible on Website'))
                                            ->default(false)
                                            ->inline(false),
                                    ]),

                                Forms\Components\Section::make(__('Location'))
                                    ->icon('heroicon-o-map-pin')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Select::make('country_id')
                                            ->label(__('Country'))
                                            ->native(false)
                                            ->searchable()
                                            ->preload()
                                            ->relationship('country', 'name')
                                            ->afterStateUpdated(fn ($set) => $set('city_id', null))
                                            ->reactive(),
                                        Forms\Components\Select::make('city_id')
                                            ->label(__('City'))
                                            ->native(false)
                                            ->searchable()
                                            ->preload()
                                            ->relationship('city', 'name')
                                            ->options(fn ($get) => TourService::getCities($get('country_id'))),
                                        Forms\Components\TextInput::make('address')
                                            ->label(__('Address'))
                                            ->maxLength(255)
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('coordinates')
                                            ->label(__('Coordinates (Lat, Lng)'))
                                            ->placeholder(__('41.2995, 69.2401'))
                                            ->helperText(__('Latitude and longitude separated by a comma'))
                                            ->formatStateUsing(fn ($record) => $record?->latitude && $record?->longitude
                                                ? $record->latitude.', '.$record->longitude : '')
                                            ->dehydrated(false)
                                            ->columnSpan(2),
                                    ]),

                                Forms\Components\Section::make(__('Business Details'))
                                    ->icon('heroicon-o-briefcase')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('company_name')
                                            ->label(__('Company Name'))
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('contract_number')
                                            ->label(__('Contract Number'))
                                            ->maxLength(255),
                                        Forms\Components\DatePicker::make('contract_date')
                                            ->label(__('Contract Date'))
                                            ->native(false),
                                        Forms\Components\TextInput::make('booking_cancellation_days')
                                            ->label(__('Cancellation Days'))
                                            ->numeric(),
                                        Forms\Components\Select::make('tour_sbor')
                                            ->label(__('Tour Service Fee'))
                                            ->options([
                                                5 => '5%',
                                                10 => '10%',
                                                15 => '15%',
                                            ]),
                                        Forms\Components\Checkbox::make('nds_included')
                                            ->label(__('VAT (NDS) Included'))
                                            ->helperText(fn () => 'Checked: this hotel\'s room prices will have VAT/NDS added automatically (currently '
                                                .rtrim(rtrim(number_format(TourService::getVatPercent(), 2), '0'), '.')
                                                .'%, set in Settings). Unchecked: prices are charged exactly as entered, with no VAT added.')
                                            ->inline(false),
                                    ]),

                                Forms\Components\Section::make(__('Contact Phones'))
                                    ->icon('heroicon-o-phone')
                                    ->schema([
                                        Forms\Components\Repeater::make('phones')
                                            ->relationship('phones')
                                            ->label(__(''))
                                            ->addActionLabel(__('+ Add Phone'))
                                            ->addActionAlignment('end')
                                            ->simple(
                                                PhoneInput::make('phone_number')
                                                    ->strictMode()
                                                    ->onlyCountries(['UZ'])
                                                    ->defaultCountry('UZ')
                                                    ->required(),
                                            ),
                                    ]),
                            ]),

                        // ── Tab 2: Media & Description ────────────────────
                        Forms\Components\Tabs\Tab::make(__('Media & Description'))
                            ->icon('heroicon-o-photo')
                            ->schema([

                                Forms\Components\Section::make(__('Photo Gallery'))
                                    ->description(__('Upload hotel photos shown on the website. First photo will be used as the cover.'))
                                    ->icon('heroicon-o-rectangle-stack')
                                    ->schema([
                                        Forms\Components\FileUpload::make('photos')
                                            ->label(__(''))
                                            ->multiple()
                                            ->image()
                                            ->reorderable()
                                            ->panelLayout('grid')
                                            ->imagePreviewHeight('130')
                                            ->maxFiles(30)
                                            ->maxSize(8192)
                                            ->directory('hotels')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->formatStateUsing(function ($record) {
                                                if (! $record) {
                                                    return [];
                                                }

                                                /** @var Hotel $record */
                                                return $record->photos
                                                    ->map(fn ($a) => $a->file_path)
                                                    ->toArray();
                                            })
                                            ->storeFiles(false),
                                    ]),

                                Forms\Components\Section::make(__('Descriptions'))
                                    ->icon('heroicon-o-document-text')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\RichEditor::make('description_en')
                                            ->label(__('Description (English)'))
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline', 'strike',
                                                'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                            ]),
                                        Forms\Components\RichEditor::make('description_ru')
                                            ->label(__('Description (Russian)'))
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline', 'strike',
                                                'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                            ]),
                                    ]),
                            ]),

                        // ── Tab 3: Facilities & Notes ─────────────────────
                        Forms\Components\Tabs\Tab::make(__('Facilities & Notes'))
                            ->icon('heroicon-o-star')
                            ->schema([

                                Forms\Components\Section::make(__('Facilities'))
                                    ->description(__('Select all amenities and services available at this hotel.'))
                                    ->icon('heroicon-o-check-circle')
                                    ->schema([
                                        Forms\Components\Select::make('facilities')
                                            ->label(__(''))
                                            ->relationship('facilities', 'name_ru')
                                            ->multiple()
                                            ->preload()
                                            ->searchable(),
                                    ]),

                                Forms\Components\Section::make(__('Internal Notes'))
                                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                    ->schema([
                                        Forms\Components\Textarea::make('comment')
                                            ->label(__('Notes / Comments'))
                                            ->rows(4)
                                            ->maxLength(1000),
                                    ]),
                            ]),

                        // ── Tab 4: Seasons & Periods ──────────────────────
                        Forms\Components\Tabs\Tab::make(__('Seasons'))
                            ->icon('heroicon-o-calendar-days')
                            ->schema([

                                Forms\Components\Section::make(__('Pricing Periods'))
                                    ->description(__('Define high / low season date ranges for this year. Room prices are set per period in the Rooms tab below.'))
                                    ->icon('heroicon-o-calendar')
                                    ->schema([
                                        Forms\Components\Repeater::make('periods')
                                            ->relationship('currentYearPeriods')
                                            ->label(__(''))
                                            ->grid(2)
                                            ->addActionLabel(__('+ Add Period'))
                                            ->addActionAlignment('end')
                                            ->collapsible()
                                            ->itemLabel(function ($get, $uuid) {
                                                $item = $get("periods.$uuid") ?? [];
                                                $from = $item['start_date'] ?? '—';
                                                $to = $item['end_date'] ?? '—';
                                                $type = $item['season_type'] ?? '';

                                                return "$from → $to".($type ? "  ($type)" : '');
                                            })
                                            ->schema([
                                                Forms\Components\Grid::make(3)->schema([
                                                    Forms\Components\DatePicker::make('start_date')
                                                        ->label(__('From'))
                                                        ->native(false)
                                                        ->required(),
                                                    Forms\Components\DatePicker::make('end_date')
                                                        ->label(__('To'))
                                                        ->native(false)
                                                        ->minDate(fn ($get) => $get('start_date'))
                                                        ->required(),
                                                    Forms\Components\Select::make('season_type')
                                                        ->label(__('Season Type'))
                                                        ->options(RoomSeasonType::class)
                                                        ->required(),
                                                ]),
                                            ]),
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
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                Tables\Filters\Filter::make('filters')
                    ->columnSpanFull()
                    ->form([
                        Forms\Components\Grid::make(['sm' => 2, 'md' => 4])->schema([
                            Forms\Components\Select::make('city_id')
                                ->label(__('City'))
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(fn () => TourService::getCities()),
                            Forms\Components\Select::make('currency')
                                ->label(__('Currency'))
                                ->native(false)
                                ->default(CurrencyEnum::UZS->value)
                                ->options(CurrencyEnum::class),
                            Forms\Components\Select::make('year')
                                ->label(__('Year'))
                                ->default(date('Y'))
                                ->native(false)
                                ->options(function () {
                                    $current = (int) date('Y');
                                    $years = range($current - 5, $current + 3);

                                    return array_combine($years, $years);
                                }),
                            Forms\Components\Select::make('season_type')
                                ->label(__('Season Type'))
                                ->native(false)
                                ->options(RoomSeasonType::class),
                        ]),
                    ])
                    ->query(fn (Builder $query, $data) => $query
                        ->when($data['city_id'], fn ($q, $v) => $q->where('city_id', $v)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['city_id'])) {
                            $indicators[] = 'City: '.City::find($data['city_id'])->name;
                        }
                        if (! empty($data['currency'])) {
                            $indicators[] = 'Currency: '.CurrencyEnum::tryFrom($data['currency'])?->getLabel();
                        }
                        if (! empty($data['year'])) {
                            $indicators[] = 'Year: '.$data['year'];
                        }
                        if (! empty($data['season_type'])) {
                            $indicators[] = 'Season: '.RoomSeasonType::tryFrom($data['season_type'])?->getLabel();
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    // info, not primary — every other "this cell opens something
                    // else" column in the app (email/phone links, group_number
                    // links) uses info; this was the one outlier.
                    ->color('info')
                    ->action(HotelPeriodsAction::make()),

                PeriodsColumn::make('room_prices')
                    ->label(__('Room prices'))
                    ->getStateUsing(function ($record, $livewire) {
                        $filters = $livewire->tableFilters;

                        return [
                            'hotel' => $record,
                            'isFirst' => $record->is($livewire->getTableRecords()->first()),
                            'currency' => $filters['filters']['currency'],
                            'year' => $filters['filters']['year'],
                            'season_type' => $filters['filters']['season_type'] ?? null,
                        ];
                    }),

                Tables\Columns\TextColumn::make('email')
                    ->url(fn ($record) => $record->email ? "mailto:{$record->email}" : null, true)
                    ->color('info')
                    ->searchable()
                    ->html(),

                Tables\Columns\TextColumn::make('inn')
                    ->searchable(),

                Tables\Columns\TextColumn::make('country.name')
                    ->label(__('Country'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('city.name')
                    ->label(__('City'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone_list')
                    ->label(__('Phones'))
                    ->getStateUsing(fn ($record) => $record->phones
                        ->map(fn ($p) => "<a href='https://t.me/{$p->phone_number}' target='_blank'>{$p->phone_number}</a>")
                        ->implode('<br/>'))
                    ->color('info')
                    ->html(),

                Tables\Columns\TextColumn::make('rate')
                    ->label(__('Rate'))
                    ->getStateUsing(fn ($record) => RateEnum::tryFrom($record->rate)?->getLabel())
                    ->sortable(),

                Tables\Columns\TextColumn::make('booking_cancellation_days')
                    ->label(__('Booking days'))
                    ->sortable(),
            ])
            ->recordUrl(null)
            ->actions([
                Tables\Actions\EditAction::make(),
            ], position: Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->authorize(fn () => auth()->user()->isAdmin())
                        // Hotels referenced by tours, hotel requests, or reviews
                        // block a plain delete at the DB level (foreign key). A
                        // raw SQLSTATE 23503 is still caught globally if one
                        // slips through, but checking first lets us delete the
                        // hotels that *are* safe and name the ones that aren't,
                        // instead of aborting the whole bulk action.
                        ->action(function (Collection $records) {
                            $blocked = [];
                            $deletable = collect();

                            foreach ($records as $hotel) {
                                $inUse = TourDayExpense::where('hotel_id', $hotel->id)->exists()
                                    || HotelRequest::where('hotel_id', $hotel->id)->exists()
                                    || $hotel->reviews()->exists();

                                if ($inUse) {
                                    $blocked[] = $hotel->name;
                                } else {
                                    $deletable->push($hotel);
                                }
                            }

                            $deletable->each->delete();

                            if (! empty($blocked)) {
                                Notification::make()
                                    ->title('Some hotels were not deleted')
                                    ->body(count($blocked)." hotel(s) are still used in tours, requests, or reviews and were skipped: {$blocked[0]}"
                                        .(count($blocked) > 1 ? ' and '.(count($blocked) - 1).' more.' : '.'))
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            }

                            if ($deletable->isNotEmpty()) {
                                Notification::make()
                                    ->title('Hotels deleted')
                                    ->body($deletable->count().' hotel(s) deleted.')
                                    ->success()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RoomTypesRelationManager::class,
            RelationManagers\HotelRulesRelationManager::class,
            RelationManagers\ReviewsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHotels::route('/'),
            'create' => Pages\CreateHotel::route('/create'),
            'edit' => Pages\EditHotel::route('/{record}/edit'),
        ];
    }
}
