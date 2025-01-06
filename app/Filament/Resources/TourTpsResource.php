<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\GuideType;
use App\Enums\TourType;
use App\Enums\TrainClass;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use App\Filament\Resources\TourTpsResource\Pages;
use App\Filament\Resources\TourTpsResource\RelationManagers;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\HotelRoomType;
use App\Models\Museum;
use App\Models\Tour;
use App\Models\User;
use App\Services\TourService;
use Filament\Forms\Components;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class TourTpsResource extends Resource
{
    use InteractsWithForms;

    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Tours TPS';
    protected static ?string $slug = 'tour-tps';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', TourType::TPS->value);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Components\TextInput::make('tour_id')
                ->formatStateUsing(function ($record) {
                    if (!empty($record)) {
                        return $record->id;
                    }
                    // last id from tours table
                    $lastId = Tour::query()->where('type', TourType::TPS)->latest()->first()->id ?? 0;
                    return $lastId + 1;
                })
                ->readOnly(),
            Components\Select::make('company_id')
                ->native(false)
                ->relationship('company', 'name')
                ->reactive()
                ->required(),
            Components\Select::make('country_id')
                ->native(false)
                ->relationship('country', 'name')
                ->afterStateUpdated(fn($get, $set) => $set('city_id', null))
                ->reactive()
                ->required(),
            Components\Select::make('city_id')
                ->native(false)
                ->relationship('city', 'name')
                ->options(fn($get) => TourService::getCities($get('country_id')))
                ->preload()
                ->reactive(),
            Components\DatePicker::make('start_date')
                ->label('Arrival time')
                ->required(),
            Components\DatePicker::make('end_date')
                ->label('Departure time')
                ->required(),
            Components\TextInput::make('pax')
                ->required()
                ->numeric(),
            Components\TextInput::make('leader_pax')
                ->numeric(),
            Components\TextInput::make('price')
                ->required()
                ->numeric(),
            Components\Textarea::make('comment'),

            Components\Select::make('guide_type')
                ->native(false)
                ->options(GuideType::class)
                ->reactive()
                ->required(),

            Components\Grid::make(3)->schema([
                Components\TextInput::make('guide_name'),
                Components\TextInput::make('guide_phone'),
                Components\TextInput::make('guide_price')
                    ->required()
                    ->numeric()
                    ->visible(fn($get) => $get('guide_type') == GuideType::Escort->value),
            ])->visible(fn($get) => $get('guide_type') == GuideType::Escort->value),

            Components\Repeater::make('days')
                ->extraAttributes(['class' => 'repeater-days'])
                ->collapsible()
                ->relationship('days')
                ->addActionLabel('Add day')
                ->columnSpanFull()
                ->addActionAlignment('end')
                ->itemLabel(function ($get, $uuid) {
                    $current = Arr::get($get('days'), $uuid);
                    $index = array_search($uuid, array_keys($get('days'))) ?? 0;
                    $index++;

                    $date = $current['date'];
                    if ($date) {
                        $date = date('d.m.Y', strtotime($date));
                        return "Day $index ($date)";
                    }

                    return "Day $index";
                })
                ->schema([
                    Components\Grid::make()->schema([
                        Components\DatePicker::make('date')
                            ->required()
                            ->reactive(),
                        Components\Select::make('city_id')
                            ->native(false)
                            ->relationship('city', 'name')
                            ->options(fn($get) => TourService::getCities($get('../../country_id')))
                            ->reactive()
                            ->preload()
                            ->required(),
                    ]),
                    Components\Repeater::make('expenses')
                        ->extraAttributes(['class' => 'repeater-expenses'])
                        ->collapsible()
                        ->itemLabel(function ($get, $uuid) {
                            $current = Arr::get($get('expenses'), $uuid);
                            $index = array_search($uuid, array_keys($get('expenses'))) ?? 0;
                            $index++;

                            $expenseType = $current['type'];
                            if ($expenseType) {
                                $expenseTypeLabel = ExpenseType::from($expenseType)->getLabel();
                                return "Expense for $expenseTypeLabel ($index)";
                            }

                            return "Expense $index";
                        })
                        ->relationship('expenses')
                        ->addActionLabel('Add expense')
                        ->addActionAlignment('end')
                        ->schema([
                            Components\Grid::make()->schema([
                                Hidden::make('index'),
                                Components\Select::make('type')
                                    ->native(false)
                                    ->label('Expense Type')
                                    ->options(ExpenseType::class)
                                    ->required()
                                    ->reactive(),

                                /*Components\TextInput::make('price')
                                    ->label('Price')
                                    ->afterStateUpdated(function ($get, $set) {
                                        $price = !empty($get('price')) ? $get('price') : 0;
                                        $pax = !empty($get('pax')) ? $get('pax') : 0;

                                        if (self::isLunch($get('type')) || $get('type') == ExpenseType::Show->value) {
                                            if (!$pax) {
                                                $set('total_price', $price);
                                            } else {
                                                $set('total_price', $price * $pax);
                                            }
                                        }

                                        if ($get('type') == ExpenseType::Conference->value) {
                                            $coffeeBreak = !empty($get('coffee_break')) ? $get('coffee_break') : 0;
                                            $set('total_price', $price + ($price * $coffeeBreak / 100));
                                        }
                                    })
                                    ->live(onBlur: true)
                                    ->required()
                                    ->visible(fn($get) => self::isPriceVisible($get('type'))),*/

                                Components\Textarea::make('comment')
                                    ->label('Comment'),

                                Components\TextInput::make('pax')
                                    ->label('Pax')
                                    ->afterStateUpdated(fn($get, $set) => TourService::onPax($get, $set))
                                    ->live(onBlur: true)
                                    ->visible(fn($get) => self::isPaxVisible($get('type'))),
                            ]),

                            // Hotel
                            Components\Grid::make()->schema([
                                Components\Select::make('hotel_id')
                                    ->native(false)
                                    ->label('Hotel')
                                    ->relationship('hotel', 'name')
                                    ->options(function ($get) {
                                        $countryId = $get('../../../../country_id');
                                        $globalCityId = $get('../../../../city_id');
                                        $localCityId = $get('../../city_id');

                                        return TourService::getHotels($localCityId, $globalCityId, $countryId);
                                    })
                                    ->preload()
                                    ->afterStateUpdated(function ($get, $set) {
                                        $set('hotel_room_type_id', null);
                                        $set('hotel_add_percent', null);
                                        $set('price', null);
                                    })
                                    ->reactive(),
                                Components\Select::make('hotel_room_type_id')
                                    ->native(false)
                                    ->label('Hotel Room Type')
                                    ->options(fn($get) => TourService::getHotelRoomTypes($get('hotel_id')))
                                    ->afterStateUpdated(function ($get, $set) {
                                        $additionalPercent = TourService::getAdditionalPercent(
                                            $get('../../../../company_id')
                                        );
                                        $price = TourService::getHotelPrice(
                                            $get('hotel_room_type_id'),
                                            $additionalPercent
                                        );
                                        $set('hotel_add_percent', $additionalPercent);
                                        $set('price', $price);
                                    })
                                    ->preload()
                                    ->reactive(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),

                            Components\Grid::make()->schema([
                                Components\Select::make('status')
                                    ->options(ExpenseStatus::class)
                                    ->native(false)
                                    ->label('Status'),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),

                            // Guide
                            Components\Grid::make()->schema([
                                Components\TextInput::make('guide_name')
                                    ->label('Guide name'),
                                Components\TextInput::make('guide_phone')
                                    ->label('Guide phone'),

                                Components\Select::make('status')
                                    ->native(false)
                                    ->options(ExpenseStatus::class)
                                    ->label('Status'),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Guide->value),

                            // Transport
                            Components\Grid::make()->schema([

                                Components\Select::make('to_city_id')
                                    ->native(false)
                                    ->label('City to')
                                    ->relationship('toCity', 'name')
                                    ->options(function ($get) {
                                        $localCityId = $get('../../city_id');
                                        if (!empty($localCityId)) {
                                            $cities = TourService::getCities($get('../../../../country_id'), false);
                                            return $cities->filter(fn($city) => $city->id != $localCityId)->pluck(
                                                'name',
                                                'id'
                                            );
                                        }

                                        return [];
                                    })
                                    ->preload()
                                    ->reactive()
                                    ->preload(),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                            Components\Grid::make()->schema([
                                Components\Select::make('transport_type')
                                    ->native(false)
                                    ->label('Transport type')
                                    ->options(TransportType::class)
                                    ->afterStateUpdated(function ($get, $set) {
                                        $price = TourService::getTransportPrice(
                                            $get('transport_type'),
                                            $get('transport_comfort_level'),
                                        );
                                        $set('price', $price);
                                    })
                                    ->reactive(),

                                Components\Select::make('transport_comfort_level')
                                    ->native(false)
                                    ->label('Comfort level')
                                    ->options(TransportComfortLevel::class)
                                    ->afterStateUpdated(function ($get, $set) {
                                        $price = TourService::getTransportPrice(
                                            $get('transport_type'),
                                            $get('transport_comfort_level'),
                                        );
                                        $set('price', $price);
                                    })
                                    ->reactive(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                            Components\Grid::make()->schema([
                                Components\Select::make('status')
                                    ->native(false)
                                    ->options(ExpenseStatus::class)
                                    ->label('Status'),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                            // Museum
                            Components\Grid::make(3)->schema([

                                Components\Grid::make()->schema([
                                    Components\Select::make('museum_id')
                                        ->label('Museum')
                                        ->native(false)
                                        ->options(function ($get) {
                                            $countryId = $get('../../../../country_id');
                                            $globalCityId = $get('../../../../city_id');
                                            $localCityId = $get('../../city_id');

                                            return TourService::getMuseums($localCityId, $globalCityId, $countryId);
                                        })
                                        ->preload()
                                        ->relationship('museum', 'name')
                                        ->reactive()
                                        ->afterStateUpdated(function ($get, $set) {
                                            $set('museum_item_id', null);

                                            $price = TourService::getMuseumPrice(
                                                $get('museum_id'),
                                                $get('pax'),
                                                $get('museum_item_id')
                                            );
                                            $set('price', $price);
                                        })
                                        ->visible(fn($get) => $get('type') == ExpenseType::Museum->value),
                                    Components\Select::make('museum_item_id')
                                        ->label('Museum Children')
                                        ->native(false)
                                        ->relationship('museumItem', 'name')
                                        ->afterStateUpdated(function ($get, $set) {
                                            $price = TourService::getMuseumPrice(
                                                $get('museum_id'),
                                                $get('pax'),
                                                $get('museum_item_id')
                                            );
                                            $set('price', $price);
                                        })
                                        ->reactive()
                                        ->disabled(function ($get) {
                                            if (!$get('museum_id')) {
                                                return true;
                                            }
                                            /** @var Museum $museum */
                                            $museum = Museum::find($get('museum_id'));
                                            return !$museum || $museum->children->count() == 0;
                                        }),
                                ]),

                                Components\Grid::make(3)->schema([
                                    Components\TextInput::make('museum_inn')
                                        ->label('Museum Inn')
                                        ->visible(fn($get) => $get('type') == ExpenseType::Museum->value),

                                    Components\TextInput::make('museum_guide')
                                        ->label('Guide')
                                        ->visible(fn($get) => $get('type') == ExpenseType::Museum->value),

                                    Components\Select::make('status')
                                        ->native(false)
                                        ->options(ExpenseStatus::class)
                                        ->label('Status')
                                        ->visible(fn($get) => $get('type') == ExpenseType::Museum->value),
                                ]),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Museum->value),

                            // Show
                            Components\Grid::make()->schema([
                                Components\TextInput::make('total_price')
                                    ->label('Total price'),

                                Components\Select::make('status')
                                    ->native(false)
                                    ->label('Status')
                                    ->options(ExpenseStatus::class),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Show->value),


                            // Train
                            Components\Grid::make()->schema([
                                Components\Select::make('from_city_id')
                                    ->native(false)
                                    ->label('City from')
                                    ->relationship('fromCity', 'name')
                                    ->options(fn($get) => TourService::getCities($get('../../../../country_id')))
                                    ->reactive()
                                    ->preload()
                                    ->visible(fn($get) => $get('type') == ExpenseType::Train->value),

                                Components\Select::make('to_city_id')
                                    ->native(false)
                                    ->label('City to')
                                    ->relationship('toCity', 'name')
                                    ->options(function ($get) {
                                        $fromCityId = $get('from_city_id');
                                        if (!empty($fromCityId)) {
                                            $cities = TourService::getCities($get('../../../../country_id'), false);
                                            return $cities->filter(fn($city) => $city->id != $fromCityId)->pluck(
                                                'name',
                                                'id'
                                            );
                                        }

                                        return [];
                                    })
                                    ->reactive()
                                    ->preload()
                                    ->visible(fn($get) => $get('type') == ExpenseType::Train->value),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Train->value),

                            Components\Grid::make()->schema([
                                Components\Select::make('train_class')
                                    ->native(false)
                                    ->label('Train class')
                                    ->options(TrainClass::class),

                                Components\Select::make('status')
                                    ->native(false)
                                    ->label('Status')
                                    ->options(ExpenseStatus::class),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Train->value),

                            Components\Grid::make()->schema([
                                Components\TimePicker::make('arrival_time')
                                    ->label('Arrival time'),

                                Components\TimePicker::make('departure_time')
                                    ->label('Departure time'),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Train->value),


                            // Plane
                            Components\Grid::make()->schema([
                                Components\Select::make('from_city_id')
                                    ->native(false)
                                    ->label('City from')
                                    ->relationship('fromCity', 'name')
                                    ->options(fn($get) => TourService::getCities($get('../../../../country_id')))
                                    ->reactive()
                                    ->preload()
                                    ->visible(fn($get) => $get('type') == ExpenseType::Plane->value),

                                Components\Select::make('to_city_id')
                                    ->native(false)
                                    ->label('City to')
                                    ->relationship('toCity', 'name')
                                    ->options(function ($get) {
                                        $fromCityId = $get('from_city_id');
                                        if (!empty($fromCityId)) {
                                            $cities = TourService::getCities($get('../../../../country_id'), false);
                                            return $cities->filter(fn($city) => $city->id != $fromCityId)->pluck(
                                                'name',
                                                'id'
                                            );
                                        }

                                        return [];
                                    })
                                    ->reactive()
                                    ->preload()
                                    ->visible(fn($get) => $get('type') == ExpenseType::Plane->value),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Plane->value),

                            Components\Grid::make()->schema([
                                Components\TimePicker::make('arrival_time')->label('Arrival time'),
                                Components\TimePicker::make('departure_time')->label('Departure time'),
                                Components\Select::make('status')
                                    ->native(false)
                                    ->label('Status')
                                    ->options(ExpenseStatus::class),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Plane->value),


                            // Conference
                            Components\Grid::make()->schema([
                                Components\TimePicker::make('conference_name')
                                    ->label('Conference name'),

                                Components\Select::make('status')
                                    ->native(false)
                                    ->label('Status')
                                    ->options(ExpenseStatus::class),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Conference->value),

                            Components\Grid::make()->schema([
                                Components\TextInput::make('coffee_break')
                                    ->suffix('%')
                                    ->afterStateUpdated(function ($get, $set) {
                                        $price = !empty($get('price')) ? $get('price') : 0;
                                        $coffeeBreak = !empty($get('coffee_break')) ? $get('coffee_break') : 0;
                                        $set('total_price', $price + ($price * $coffeeBreak / 100));
                                    })
                                    ->live(onBlur: true)
                                    ->label('Coffee break'),

                                Components\TextInput::make('total_price')
                                    ->label('Total price'),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Conference->value),


                            // Lunch and Dinner
                            Components\Grid::make()->schema([
                                Components\Select::make('restaurant_id')
                                    ->native(false)
                                    ->label('Restaurant')
                                    ->relationship('restaurant', 'name')
                                    ->options(function ($get) {
                                        $countryId = $get('../../../../country_id');
                                        $globalCityId = $get('../../../../city_id');
                                        $localCityId = $get('../../city_id');

                                        return TourService::getRestaurants($localCityId, $globalCityId, $countryId);
                                    })
                                    ->reactive()
                                    ->preload()
                                    ->visible(fn($get) => self::isLunch($get('type'))),

                                Components\TextInput::make('total_price')
                                    ->label('Total price')
                                    ->visible(fn($get) => self::isLunch($get('type'))),

                            ])->visible(fn($get) => self::isLunch($get('type'))),

                            // Other
                            Components\Grid::make()->schema([

                                Components\TextInput::make('other_name')
                                    ->label('Name')
                                    ->visible(fn($get) => $get('type') == ExpenseType::Other->value),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Other->value),

                        ])
                ])
        ]);
    }

    public static function isPriceVisible($expenseType): bool
    {
        return in_array($expenseType, [
//            ExpenseType::Hotel->value,
            ExpenseType::Guide->value,
            ExpenseType::Transport->value,
            ExpenseType::Train->value,
            ExpenseType::Plane->value,
            ExpenseType::Show->value,
            ExpenseType::Conference->value,
            ExpenseType::Museum->value,
            ExpenseType::Lunch->value,
            ExpenseType::Dinner->value
        ]);
    }

    public static function isPaxVisible($expenseType): bool
    {
        return in_array($expenseType, [
//            ExpenseType::Hotel->value,
//            ExpenseType::Guide->value,
            ExpenseType::Transport->value,
            ExpenseType::Train->value,
            ExpenseType::Plane->value,
            ExpenseType::Show->value,
            ExpenseType::Conference->value,
            ExpenseType::Museum->value,
            ExpenseType::Lunch->value,
            ExpenseType::Dinner->value
        ]);
    }

    public static function isLunch($expenseType): bool
    {
        return in_array($expenseType, [ExpenseType::Lunch->value, ExpenseType::Dinner->value]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->filters([
                Tables\Filters\Filter::make('country_id')
                    ->label('Group number')
                    ->form([
                        Components\Select::make('country_id')
                            ->native(false)
                            ->relationship('country', 'name')
                            ->options(Country::all()->pluck('name', 'id')->toArray()),
                        Components\Select::make('city_id')
                            ->native(false)
                            ->relationship('city', 'name')
                            ->options(fn($get) => TourService::getCities($get('country_id')))
                            ->preload(),
                        Components\Select::make('company_id')
                            ->native(false)
                            ->relationship('company', 'name')
                            ->options(Company::query()->pluck('name', 'id')->toArray()),
                        Components\Select::make('created_by')
                            ->label('Admin creator')
                            ->native(false)
                            ->relationship('createdBy', 'name')
                            ->options(User::query()->pluck('name', 'id')->toArray()),

                        Components\DatePicker::make('created_from'),
                        Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, $data) {
                        return $query
                            ->when(
                                $data['country_id'],
                                fn($query, $countryId) => $query->where('country_id', $countryId)
                            )
                            ->when($data['city_id'], fn($query, $cityId) => $query->where('city_id', $cityId))
                            ->when(
                                $data['company_id'],
                                fn($query, $companyId) => $query->where('company_id', $companyId)
                            )
                            ->when(
                                $data['created_by'],
                                fn($query, $createdBy) => $query->where('created_by', $createdBy)
                            )
                            ->when(
                                $data['created_from'],
                                fn($query, $createdFrom) => $query->whereDate('created_at', '>=', $createdFrom)
                            )
                            ->when(
                                $data['created_until'],
                                fn($query, $createdUntil) => $query->whereDate('created_at', '<=', $createdUntil)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['country_id'] ?? null) {
                            $indicators['country_id'] = 'Country: ' . Country::find($data['country_id'])->name;
                        }
                        if ($data['city_id'] ?? null) {
                            $indicators['city_id'] = 'City: ' . City::find($data['city_id'])->name;
                        }
                        if ($data['company_id'] ?? null) {
                            $indicators['company_id'] = 'Company: ' . Company::find($data['company_id'])->name;
                        }
                        if ($data['created_by'] ?? null) {
                            $indicators['created_by'] = 'Admin creator: ' . User::find($data['created_by'])->name;
                        }
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Order from ' . Carbon::parse(
                                    $data['created_from']
                                )->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Order until ' . Carbon::parse(
                                    $data['created_until']
                                )->toFormattedDateString();
                        }

                        return $indicators;
                    })
            ])
            ->columns([
                Columns\TextColumn::make('group_number')
                    ->searchable(),
                Columns\TextColumn::make('company.name')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('price')
                    ->formatStateUsing(function ($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('expenses')
                    ->badge(fn(Tour $record) => TourService::isVisible($record))
                    ->color('danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->formatStateUsing(function ($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('income')
                    ->badge(fn(Tour $record) => TourService::isVisible($record))
                    ->color(fn(Tour $record) => $record->income > 0 ? 'success' : 'danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->formatStateUsing(function ($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('createdBy.name')
                    ->sortable(),
                Columns\TextColumn::make('createdBy.operator_percent_tps')
                    ->label('Operator %')
                    ->suffix('%')
                    ->sortable(),
                Columns\TextColumn::make('country.name'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                /*Columns\TextColumn::make('status')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('pax')
                    ->numeric()
                    ->sortable(),*/
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin())
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
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }
}
