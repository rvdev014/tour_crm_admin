<?php

namespace App\Filament\Resources;

use App\Enums\CompanyType;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\GuideType;
use App\Enums\TourType;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use App\Filament\Resources\TourTpsResource\Pages;
use App\Filament\Resources\TourTpsResource\RelationManagers;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
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
            Components\Fieldset::make('Tour details')->schema([
                Components\TextInput::make('group_number')
                    ->formatStateUsing(function ($record) {
                        if (!empty($record)) {
                            return $record->group_number;
                        }
                        return TourService::getGroupNumber(TourType::TPS);
                    })
                    ->readOnly(),
                Components\Select::make('company_id')
                    ->native(false)
                    ->relationship('company', 'name')
                    ->options(TourService::getCompanies(CompanyType::TPS))
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
                    ->displayFormat('d.m.Y')
                    ->label('Arrival time')
                    ->required(),
                Components\DatePicker::make('end_date')
                    ->displayFormat('d.m.Y')
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
            ]),

            Components\Fieldset::make('Guide info')->schema([
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
                        ->numeric(),
                ])->visible(fn($get) => $get('guide_type') == GuideType::Escort->value)
            ]),

            // Add section with subtitle
            Components\Fieldset::make('Rooming')
                ->schema(TourService::generateRoomingSchema()),

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
                            ->displayFormat('d.m.Y')
                            ->required()
                            ->reactive(),
                        Components\Select::make('city_id')
                            ->native(false)
                            ->relationship('city', 'name')
                            ->options(fn($get) => TourService::getCities())
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
                                    ->options(function ($get) {
                                        $options = ExpenseType::casesOptions();
                                        unset($options[ExpenseType::Conference->value]);
                                        if ($get('../../../../guide_type') == GuideType::Escort->value) {
                                            unset($options[ExpenseType::Guide->value]);
                                        }
                                        return $options;
                                    })
                                    ->required()
                                    ->reactive(),
                            ]),

                            // Hotel
                            Components\Fieldset::make('Hotel info')->schema([
                                Components\Grid::make()->schema([
                                    Components\Select::make('hotel_id')
                                        ->native(false)
                                        ->label('Hotel')
                                        ->relationship('hotel', 'name')
                                        ->options(fn ($get) => TourService::getHotels($get('../../city_id')))
                                        ->preload()
                                        ->reactive(),
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->native(false)
                                        ->label('Status'),
                                    Components\Textarea::make('comment')
                                        ->label('Comment')
                                        ->columnSpanFull(),
                                ])
                            ])->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),

                            // Guide
                            Components\Fieldset::make('Guide info')->schema([
                                Components\Grid::make()->schema([
                                    Components\TextInput::make('guide_name')
                                        ->label('Guide name'),
                                    Components\TextInput::make('guide_phone')
                                        ->label('Guide phone'),

                                    Components\Select::make('status')
                                        ->native(false)
                                        ->options(ExpenseStatus::class)
                                        ->label('Status'),

                                    Components\TextInput::make('guide_price')
                                        ->statePath('price')
                                        ->label('Price'),

                                    Components\Textarea::make('comment')
                                        ->label('Comment')
                                        ->columnSpanFull(),
                                ]),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Guide->value),

                            // Transport
                            Components\Fieldset::make('Transport info')->schema([

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

                                Components\Select::make('to_city_id')
                                    ->native(false)
                                    ->label('City to')
                                    ->relationship('toCity', 'name')
                                    ->options(TourService::getCities())
                                    ->preload()
                                    ->reactive()
                                    ->preload(),

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

                                Components\Select::make('status')
                                    ->native(false)
                                    ->options(ExpenseStatus::class)
                                    ->label('Status'),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                            // Museum
                            Components\Fieldset::make('Museum info')->schema([

                                Components\Select::make('museum_id')
                                    ->label('Museum')
                                    ->native(false)
                                    ->relationship('museum', 'name')
                                    ->options(fn ($get) => TourService::getMuseums($get('../../city_id')))
                                    ->createOptionForm([
                                        Components\Grid::make()->schema([
                                            Components\TextInput::make('name')
                                                ->required()
                                                ->maxLength(255),
                                            Components\TextInput::make('inn')
                                                ->required()
                                                ->maxLength(255),
                                            Components\Select::make('country_id')
                                                ->native(false)
                                                ->relationship('country', 'name')
                                                ->afterStateUpdated(fn($get, $set) => $set('city_id', null))
                                                ->reactive(),
                                            Components\Select::make('city_id')
                                                ->native(false)
                                                ->relationship('city', 'name')
                                                ->options(fn($get) => TourService::getCities($get('country_id')))
                                                ->preload()
                                                ->reactive(),
                                            Components\TextInput::make('price_per_person')
                                                ->required()
                                                ->numeric(),
                                        ])
                                    ])
                                    ->preload()
                                    ->reactive(),
                                Components\Select::make('museum_item_id')
                                    ->label('Museum Children')
                                    ->native(false)
                                    ->relationship('museumItem', 'name')
                                    ->reactive()
                                    ->disabled(function ($get) {
                                        if (!$get('museum_id')) {
                                            return true;
                                        }
                                        /** @var Museum $museum */
                                        $museum = Museum::find($get('museum_id'));
                                        return !$museum || $museum->children->count() == 0;
                                    }),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Museum->value),

                            // Lunch and Dinner
                            Components\Fieldset::make('Lunch / Dinner info')->schema([

                                Components\Select::make('restaurant_id')
                                    ->native(false)
                                    ->label('Restaurant')
                                    ->relationship('restaurant', 'name')
                                    ->options(fn ($get) => TourService::getRestaurants($get('../../city_id')))
                                    ->reactive()
                                    ->preload(),

                                Components\Select::make('status')
                                    ->options(ExpenseStatus::class)
                                    ->native(false)
                                    ->label('Status'),
                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => self::isLunch($get('type'))),

                            // Train
                            Components\Fieldset::make('Train info')->schema([

                                Components\Grid::make(3)->schema([
                                    Components\Select::make('train_name')
                                        ->native(false)
                                        ->label('Train name')
                                        ->options([
                                            'sharq' => 'Sharq',
                                            'afrosiyob' => 'Afrosiyob',
                                        ]),

                                    Components\Select::make('to_city_id')
                                        ->native(false)
                                        ->label('City to')
                                        ->relationship('toCity', 'name')
                                        ->options(TourService::getCities())
                                        ->reactive()
                                        ->preload(),

                                    Components\TextInput::make('price')
                                        ->label('Price')
                                        ->required(),
                                ]),

                                Components\Grid::make(3)->schema([
                                    Components\TextInput::make('train_class_economy')
                                        ->label('Economy')
                                        ->numeric(),
                                    Components\TextInput::make('train_class_vip')
                                        ->label('VIP')
                                        ->numeric(),
                                    Components\TextInput::make('train_class_second')
                                        ->label('Second')
                                        ->numeric(),
                                ]),

                                Components\Grid::make(3)->schema([
                                    Components\TimePicker::make('departure_time')
                                        ->label('Departure time'),
                                    Components\TimePicker::make('arrival_time')
                                        ->label('Arrival time'),
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->native(false)
                                        ->label('Status'),
                                ]),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Train->value),

                            // Show
                            Components\Fieldset::make('Show info')->schema([

                                Components\Grid::make()->schema([
                                    Components\Select::make('show_id')
                                        ->native(false)
                                        ->label('Show')
                                        ->relationship('show', 'name')
                                        ->options(fn ($get) => TourService::getShows($get('../../city_id')))
                                        ->reactive()
                                        ->preload()
                                        ->required(),
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->native(false)
                                        ->label('Status'),
                                    Components\Textarea::make('comment')
                                        ->label('Comment')
                                        ->columnSpanFull(),
                                ]),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Show->value),

                            // Plane
                            Components\Fieldset::make('Plane info')->schema([

                                Components\TextInput::make('price')
                                    ->label('Price')
                                    ->required(),

                                Components\Select::make('to_city_id')
                                    ->native(false)
                                    ->label('City to')
                                    ->relationship('toCity', 'name')
                                    ->options(TourService::getCities())
                                    ->reactive()
                                    ->preload(),

                                Components\Grid::make(3)->schema([
                                    Components\TimePicker::make('departure_time')->label('Departure time'),

                                    Components\TimePicker::make('arrival_time')->label('Arrival time'),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->native(false)
                                        ->label('Status'),

                                ]),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Plane->value),

                            // Other
                            Components\Fieldset::make('Plane info')->schema([
                                Components\TextInput::make('other_name')
                                    ->label('Name'),

                                Components\TextInput::make('price')
                                    ->label('Price')
                                    ->required(),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Other->value),

                        ])
                ])
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
