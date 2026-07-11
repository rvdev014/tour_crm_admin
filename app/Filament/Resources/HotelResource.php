<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\City;
use App\Models\Hotel;
use App\Models\HotelPeriod;
use App\Enums\RateEnum;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Enums\CurrencyEnum;
use App\Enums\RoomSeasonType;
use App\Services\TourService;
use Filament\Resources\Resource;
use App\Tables\Columns\PeriodsColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\HotelResource\Pages;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use App\Filament\Resources\HotelResource\RelationManagers;

class HotelResource extends Resource
{
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
            'Email'   => $record->email,
            'Address' => $record->address,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->disabled(fn() => auth()->user()->isOperator())
            ->schema([
                Forms\Components\Tabs::make('Hotel')
                    ->tabs([

                        // ── Tab 1: Basic Information ─────────────────────
                        Forms\Components\Tabs\Tab::make('Basic Info')
                            ->icon('heroicon-o-information-circle')
                            ->schema([

                                Forms\Components\Section::make('Hotel Identity')
                                    ->icon('heroicon-o-building-office')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Hotel Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(2),
                                        Forms\Components\Select::make('rate')
                                            ->label('Star Rating')
                                            ->options(function () {
                                                $options = [];
                                                foreach (RateEnum::cases() as $rate) {
                                                    $options[$rate->value] = $rate->getLabel();
                                                }
                                                return $options;
                                            }),
                                        Forms\Components\TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->suffixAction(function ($record) {
                                                if (!$record?->email) return [];
                                                return [
                                                    Forms\Components\Actions\Action::make('hotel_email')
                                                        ->icon('heroicon-o-paper-airplane')
                                                        ->url("mailto:{$record->email}", true),
                                                ];
                                            }),
                                        Forms\Components\TextInput::make('inn')
                                            ->label('INN'),
                                        Forms\Components\Toggle::make('is_visible')
                                            ->label('Visible on Website')
                                            ->default(false)
                                            ->inline(false),
                                    ]),

                                Forms\Components\Section::make('Location')
                                    ->icon('heroicon-o-map-pin')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Select::make('country_id')
                                            ->label('Country')
                                            ->native(false)
                                            ->searchable()
                                            ->preload()
                                            ->relationship('country', 'name')
                                            ->afterStateUpdated(fn($set) => $set('city_id', null))
                                            ->reactive(),
                                        Forms\Components\Select::make('city_id')
                                            ->label('City')
                                            ->native(false)
                                            ->searchable()
                                            ->preload()
                                            ->relationship('city', 'name')
                                            ->options(fn($get) => TourService::getCities($get('country_id'))),
                                        Forms\Components\TextInput::make('address')
                                            ->label('Address')
                                            ->maxLength(255)
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('coordinates')
                                            ->label('Coordinates (Lat, Lng)')
                                            ->placeholder('41.2995, 69.2401')
                                            ->helperText('Latitude and longitude separated by a comma')
                                            ->formatStateUsing(fn($record) => $record?->latitude && $record?->longitude
                                                ? $record->latitude . ', ' . $record->longitude : '')
                                            ->dehydrated(false)
                                            ->columnSpan(2),
                                    ]),

                                Forms\Components\Section::make('Business Details')
                                    ->icon('heroicon-o-briefcase')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('company_name')
                                            ->label('Company Name')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('contract_number')
                                            ->label('Contract Number')
                                            ->maxLength(255),
                                        Forms\Components\DatePicker::make('contract_date')
                                            ->label('Contract Date')
                                            ->native(false),
                                        Forms\Components\TextInput::make('booking_cancellation_days')
                                            ->label('Cancellation Days')
                                            ->numeric(),
                                        Forms\Components\Select::make('tour_sbor')
                                            ->label('Tour Service Fee')
                                            ->options([
                                                5  => '5%',
                                                10 => '10%',
                                                15 => '15%',
                                            ]),
                                        Forms\Components\Checkbox::make('nds_included')
                                            ->label('VAT (NDS) Included')
                                            ->inline(false),
                                    ]),

                                Forms\Components\Section::make('Contact Phones')
                                    ->icon('heroicon-o-phone')
                                    ->schema([
                                        Forms\Components\Repeater::make('phones')
                                            ->relationship('phones')
                                            ->label('')
                                            ->addActionLabel('+ Add Phone')
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
                        Forms\Components\Tabs\Tab::make('Media & Description')
                            ->icon('heroicon-o-photo')
                            ->schema([

                                Forms\Components\Section::make('Photo Gallery')
                                    ->description('Upload hotel photos shown on the website. First photo will be used as the cover.')
                                    ->icon('heroicon-o-rectangle-stack')
                                    ->schema([
                                        Forms\Components\FileUpload::make('photos')
                                            ->label('')
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
                                                if (!$record) return [];
                                                /** @var Hotel $record */
                                                return $record->attachments
                                                    ->map(fn($a) => $a->file_path)
                                                    ->toArray();
                                            })
                                            ->storeFiles(false),
                                    ]),

                                Forms\Components\Section::make('Descriptions')
                                    ->icon('heroicon-o-document-text')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\RichEditor::make('description_en')
                                            ->label('Description (English)')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline', 'strike',
                                                'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                            ]),
                                        Forms\Components\RichEditor::make('description_ru')
                                            ->label('Description (Russian)')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline', 'strike',
                                                'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                            ]),
                                    ]),
                            ]),

                        // ── Tab 3: Facilities & Notes ─────────────────────
                        Forms\Components\Tabs\Tab::make('Facilities & Notes')
                            ->icon('heroicon-o-star')
                            ->schema([

                                Forms\Components\Section::make('Facilities')
                                    ->description('Select all amenities and services available at this hotel.')
                                    ->icon('heroicon-o-check-circle')
                                    ->schema([
                                        Forms\Components\Select::make('facilities')
                                            ->label('')
                                            ->relationship('facilities', 'name_ru')
                                            ->multiple()
                                            ->preload()
                                            ->searchable(),
                                    ]),

                                Forms\Components\Section::make('Internal Notes')
                                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                    ->schema([
                                        Forms\Components\Textarea::make('comment')
                                            ->label('Notes / Comments')
                                            ->rows(4)
                                            ->maxLength(1000),
                                    ]),
                            ]),

                        // ── Tab 4: Seasons & Periods ──────────────────────
                        Forms\Components\Tabs\Tab::make('Seasons')
                            ->icon('heroicon-o-calendar-days')
                            ->schema([

                                Forms\Components\Section::make('Pricing Periods')
                                    ->description('Define high / low season date ranges for this year. Room prices are set per period in the Rooms tab below.')
                                    ->icon('heroicon-o-calendar')
                                    ->schema([
                                        Forms\Components\Repeater::make('periods')
                                            ->relationship('currentYearPeriods')
                                            ->label('')
                                            ->grid(2)
                                            ->addActionLabel('+ Add Period')
                                            ->addActionAlignment('end')
                                            ->collapsible()
                                            ->itemLabel(function ($get, $uuid) {
                                                $item = $get("periods.$uuid") ?? [];
                                                $from = $item['start_date'] ?? '—';
                                                $to   = $item['end_date']   ?? '—';
                                                $type = $item['season_type'] ?? '';
                                                return "$from → $to" . ($type ? "  ($type)" : '');
                                            })
                                            ->schema([
                                                Forms\Components\Grid::make(3)->schema([
                                                    Forms\Components\DatePicker::make('start_date')
                                                        ->label('From')
                                                        ->native(false)
                                                        ->required(),
                                                    Forms\Components\DatePicker::make('end_date')
                                                        ->label('To')
                                                        ->native(false)
                                                        ->minDate(fn($get) => $get('start_date'))
                                                        ->required(),
                                                    Forms\Components\Select::make('season_type')
                                                        ->label('Season Type')
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
                        Forms\Components\Grid::make(6)->schema([
                            Forms\Components\Select::make('city_id')
                                ->label('City')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(fn() => TourService::getCities()),
                            Forms\Components\Select::make('currency')
                                ->label('Currency')
                                ->native(false)
                                ->default(CurrencyEnum::UZS->value)
                                ->options(CurrencyEnum::class),
                            Forms\Components\Select::make('year')
                                ->label('Year')
                                ->default(date('Y'))
                                ->native(false)
                                ->options(function () {
                                    $current = (int) date('Y');
                                    $years = range($current - 5, $current + 3);
                                    return array_combine($years, $years);
                                }),
                            Forms\Components\Select::make('season_type')
                                ->label('Season Type')
                                ->native(false)
                                ->options(RoomSeasonType::class),
                        ]),
                    ])
                    ->query(fn(Builder $query, $data) => $query
                        ->when($data['city_id'], fn($q, $v) => $q->where('city_id', $v)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (!empty($data['city_id'])) {
                            $indicators[] = 'City: ' . City::find($data['city_id'])->name;
                        }
                        if (!empty($data['currency'])) {
                            $indicators[] = 'Currency: ' . CurrencyEnum::tryFrom($data['currency'])?->getLabel();
                        }
                        if (!empty($data['year'])) {
                            $indicators[] = 'Year: ' . $data['year'];
                        }
                        if (!empty($data['season_type'])) {
                            $indicators[] = 'Season: ' . RoomSeasonType::tryFrom($data['season_type'])?->getLabel();
                        }
                        return $indicators;
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('season_type')
                    ->label('Season type')
                    ->placeholder('—')
                    ->getStateUsing(function ($record, $livewire) {
                        $year = (int) ($livewire->tableFilters['filters']['year'] ?? now()->year);

                        return HotelPeriod::highestPriorityFor($record->id, $year)?->season_type;
                    })
                    ->tooltip(function ($record, $livewire) {
                        $year = (int) ($livewire->tableFilters['filters']['year'] ?? now()->year);
                        $period = HotelPeriod::highestPriorityFor($record->id, $year);

                        return $period
                            ? $period->start_date->format('d.m.Y') . ' — ' . $period->end_date->format('d.m.Y')
                            : null;
                    }),

                PeriodsColumn::make('room_prices')
                    ->label('Room prices')
                    ->getStateUsing(function ($record, $livewire) {
                        $filters = $livewire->tableFilters;
                        return [
                            'hotel'       => $record,
                            'isFirst'     => $record->is($livewire->getTableRecords()->first()),
                            'currency'    => $filters['filters']['currency'],
                            'year'        => $filters['filters']['year'],
                            'season_type' => $filters['filters']['season_type'] ?? null,
                        ];
                    }),

                Tables\Columns\TextColumn::make('email')
                    ->url(fn($record) => $record->email ? "mailto:{$record->email}" : null, true)
                    ->color('info')
                    ->searchable()
                    ->html(),

                Tables\Columns\TextColumn::make('inn')
                    ->searchable(),

                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('city.name')
                    ->label('City')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone_list')
                    ->label('Phones')
                    ->getStateUsing(fn($record) => $record->phones
                        ->map(fn($p) => "<a href='https://t.me/{$p->phone_number}' target='_blank'>{$p->phone_number}</a>")
                        ->implode('<br/>'))
                    ->color('info')
                    ->html(),

                Tables\Columns\TextColumn::make('rate')
                    ->label('Rate')
                    ->getStateUsing(fn($record) => RateEnum::tryFrom($record->rate)?->getLabel())
                    ->sortable(),

                Tables\Columns\TextColumn::make('booking_cancellation_days')
                    ->label('Booking days')
                    ->sortable(),
            ])
            ->recordUrl(null)
            ->actions([
                Tables\Actions\EditAction::make(),
            ], position: Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RoomTypesRelationManager::class,
            RelationManagers\HotelRulesRelationManager::class,
            RelationManagers\ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHotels::route('/'),
            'create' => Pages\CreateHotel::route('/create'),
            'edit'   => Pages\EditHotel::route('/{record}/edit'),
        ];
    }
}
