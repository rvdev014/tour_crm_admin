<?php

namespace App\Filament\Resources;

use App\Models\City;
use App\Models\Tour;
use App\Models\User;
use Filament\Tables;
use App\Enums\TourType;
use App\Models\Company;
use App\Models\Country;
use App\Enums\PlaneType;
use App\Models\RoomType;
use App\Models\HotelRoomType;
use Filament\Forms\Form;
use App\Enums\CompanyType;
use App\Enums\ExpenseType;
use App\Enums\PaymentType;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentStatus;
use Filament\Tables\Columns;
use App\Services\TourService;
use Filament\Forms\Components;
use Illuminate\Support\Carbon;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\TourCorporateResource\Pages;
use App\Filament\Resources\TourCorporateResource\RelationManagers;
use App\Filament\Resources\TourCorporateResource\Actions\StatusAction;

class TourCorporateResource extends Resource
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
    protected static ?string $model = Tour::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Tours';
    protected static ?string $navigationLabel = 'Corporate';
    protected static ?string $slug = 'tour-corporate';
    protected static ?int $navigationSort = 2;
    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', TourType::Corporate->value);
    }
    
    public static function form(Form $form): Form
    {
        return $form->schema([
            Components\Section::make(__('Tour details'))
                ->icon('heroicon-o-document-text')
                ->description(__('Company, payment status, and the dates the group travels.'))
                ->schema([

                Components\Grid::make(4)->schema([
                    Components\TextInput::make('group_number')
                        ->formatStateUsing(function($record) {
                            if (!empty($record)) {
                                return $record->group_number;
                            }
                            return TourService::getGroupNumber(TourType::Corporate);
                        })
                        ->readOnly(),
                    
                    Components\Select::make('company_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->relationship('company', 'name')
                        ->options(TourService::getCompanies([CompanyType::Corporate]))
                        ->reactive()
                        ->required(),
                    
                    Components\Select::make('payment_type')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(PaymentType::class)
                        ->reactive(),
                    
                    Components\Select::make('payment_status')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(PaymentStatus::class)
                        ->reactive(),
                    
                    /*Components\Repeater::make('passengers')
                        ->relationship('passengers')
                        ->addActionLabel('Add passenger')
                        ->minItems(1)
                        ->simple(
                            Components\TextInput::make('name')
                                ->label('Passenger name')
                                ->required(),
                        ),*/
                ]),
                
                Components\Grid::make(4)->schema([
                    Components\DateTimePicker::make('start_date')
                        ->displayFormat('d.m.Y H:i')
                        ->label(__('Start date'))
                        ->seconds(false)
                        ->afterStateUpdated(function($get, $set) {
                            if (Carbon::parse($get('end_date')) < Carbon::parse($get('start_date'))) {
                                $set('end_date', null);
                            }
                        })
                        ->reactive(),
                    Components\DateTimePicker::make('end_date')
                        ->displayFormat('d.m.Y H:i')
                        ->label(__('End date'))
                        ->seconds(false)
                        ->minDate(fn($get) => $get('start_date') ? Carbon::parse($get('start_date'))->addDay()->format('d.m.Y H:i') : null)
                        ->reactive(),
                    Components\TextInput::make('requested_by'),
                    Components\TextInput::make('fit')
                        ->label(__('Табличка')),
                    Components\Textarea::make('comment')
                ]),
            ]),
            
            Components\Repeater::make('groups')
                ->extraAttributes(['class' => 'repeater-days'])
                ->collapsed(fn($record) => !empty($record->id))
                ->columnSpanFull()
                ->collapsible()
                ->itemLabel(function($get, $uuid) {
                    $current = Arr::get($get('groups'), $uuid);
                    
                    $expenseTypes = [];
                    foreach ($current['expenses'] ?? [] as $expense) {
                        $expenseType = $expense['type'] ?? null;
                        if ($expenseType && !array_key_exists($expenseType, $expenseTypes)) {
                            $expenseTypes[$expenseType] = ExpenseType::from($expenseType)->getLabel();
                        }
                    }
                    
                    $index = array_search($uuid, array_keys($get('groups'))) ?? 0;
                    $index++;

                    $passengerCount = count($current['passengers'] ?? []);
                    $summary = $expenseTypes
                        ? __('Expenses: :types', ['types' => implode(', ', $expenseTypes)])
                        : __('No expenses yet');

                    return __('Group :index', ['index' => $index]).' · '.trans_choice('{1} 1 passenger|[2,*] :count passengers', $passengerCount, ['count' => $passengerCount]).' · '.$summary;
                })
                ->relationship('groups')
                ->addActionLabel(__('Add group'))
                ->addActionAlignment('end')
                ->schema([
                    Components\Grid::make(3)->schema([
                        Components\Repeater::make('passengers')
                            ->relationship('passengers')
                            ->addActionLabel(__('Add passenger'))
                            ->minItems(1)
                            ->simple(
                                Components\TextInput::make('name')
                                    ->label(__('Passenger name'))
                                    ->required(),
                            ),
                    ]),
                    
                    Components\Repeater::make('expenses')
                        ->extraAttributes(['class' => 'repeater-expenses'])
                        ->collapsed(fn($record) => !empty($record->id))
                        ->columnSpanFull()
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(function($get, $uuid) {
                            $current = Arr::get($get('expenses'), $uuid);
                            $index = array_search($uuid, array_keys($get('expenses'))) ?? 0;
                            $index++;

                            $expenseType = $current['type'] ?? null;
                            if ($expenseType) {
                                $expenseTypeLabel = ExpenseType::from($expenseType)->getLabel();
                                $currentStatus = $current['status'] ?? null;
                                $status = $currentStatus ? ' - ' . strtoupper(ExpenseStatus::from($currentStatus)->getLabel()) : '';

                                $date = $current['date'] ?? null;
                                $dateLabel = $date ? ' | ' . Carbon::parse($date)->format('d.m.Y') : '';

                                return __('Expense for :type (:index):date:status', [
                                    'type' => $expenseTypeLabel,
                                    'index' => $index,
                                    'date' => $dateLabel,
                                    'status' => $status,
                                ]);
                            }

                            return __('Expense :index', ['index' => $index]);
                        })
                        ->relationship('expenses')
                        ->addActionLabel(__('Add expense'))
                        ->addActionAlignment('end')
                        ->schema([
                            Components\Section::make(__('Expense details'))
                                ->icon('heroicon-o-tag')
                                ->description(__('What kind of expense this is, and when/where it applies.'))
                                ->schema([
                                Components\Grid::make(3)->schema([
                                Hidden::make('index'),
                                Hidden::make('price_currency'),
                                Components\Select::make('type')
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->label(__('Expense Type'))
                                    ->options(function() {
                                        $options = ExpenseType::casesOptions();
                                        return collect($options)->filter(fn($value) => in_array($value, [
                                            ExpenseType::Hotel->getLabel(),
                                            ExpenseType::Transport->getLabel(),
                                            ExpenseType::Train->getLabel(),
                                            ExpenseType::Flight->getLabel(),
                                            ExpenseType::Extra->getLabel(),
                                            ExpenseType::Conference->getLabel(),
                                        ]))->toArray();
                                    })
                                    ->required()
                                    ->reactive(),
                                Components\DatePicker::make('date')
                                    ->label(function($get) {
                                        if ($get('type') == ExpenseType::Flight->value) {
                                            return __('Flight date');
                                        }
                                        return __('Date');
                                    })
                                    ->displayFormat('d.m.Y')
                                    ->native(false)
                                    ->hidden(fn($get) => in_array($get('type'), [
                                        ExpenseType::Transport->value,
                                        ExpenseType::Hotel->value,
                                    ]))
                                    ->reactive(),
                                Components\Select::make('city_id')
                                    ->native(false)
                                    ->label(function($get) {
                                        if ($get('type') == ExpenseType::Train->value) {
                                            return __('City from');
                                        }
                                        return __('City');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->options(fn($get) => TourService::getCities())
                                    ->reactive()
                                    ->preload()
                                    ->required()
                                    ->hidden(fn($get) => in_array($get('type'), [
                                        ExpenseType::Flight->value,
                                    ])),
                                ]),
                            ]),

                            // Hotel
                            Components\Section::make(__('Hotel info'))
                                ->icon('heroicon-o-building-office-2')
                                ->schema([
                                Components\Grid::make(3)->schema([
                                    Components\Select::make('hotel_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Hotel'))
                                        ->options(fn($get) => TourService::getHotels($get('city_id')))
                                        ->preload()
                                        ->reactive()
                                        ->required(),
                                    Components\DateTimePicker::make('date')
                                        ->label(__('Check-in date & time'))
                                        ->native(false)
                                        ->displayFormat('d.m.Y H:i')
                                        ->seconds(false)
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function($state, $get, $set) {
                                            $set(
                                                'hotel_total_nights',
                                                TourService::calculateHotelNights(
                                                    $get('hotel_id'),
                                                    $state,
                                                    $state ? Carbon::parse($state)->format('H:i') : null,
                                                    $get('hotel_checkout_date_time')
                                                )
                                            );
                                        }),
                                    Components\DateTimePicker::make('hotel_checkout_date_time')
                                        ->native(false)
                                        ->displayFormat('d.m.Y H:i')
                                        ->seconds(false)
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function($get, $set) {
                                            $checkIn = $get('date');
                                            $set(
                                                'hotel_total_nights',
                                                TourService::calculateHotelNights(
                                                    $get('hotel_id'),
                                                    $checkIn,
                                                    $checkIn ? Carbon::parse($checkIn)->format('H:i') : null,
                                                    $get('hotel_checkout_date_time')
                                                )
                                            );
                                        })
                                        ->label(__('Check-out date & time')),
                                ]),
                                
                                Components\Grid::make(3)->schema([
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Status')),
                                    Components\TextInput::make('hotel_total_nights')
                                        ->numeric()
                                        ->label(__('Total nights')),
                                    Components\Textarea::make('comment')->label(__('Comment')),
                                ]),
                                
                                Components\Repeater::make('roomTypes')
                                    ->grid(2)
                                    ->columnSpanFull()
                                    ->relationship('roomTypes')
                                    ->addActionLabel(__('Add room type'))
                                    ->schema([
                                        Components\Grid::make(3)->schema([
                                            Components\Select::make('room_type_id')
                                                ->native(false)
                                                ->searchable()
                                                ->preload()
                                                ->label(__('Room type'))
                                                ->options(function($get) {
                                                    $hotelId = $get('../../hotel_id');
                                                    if (!$hotelId) {
                                                        return [];
                                                    }
                                                    return RoomType::query()
                                                        ->whereIn('id', HotelRoomType::query()
                                                            ->where('hotel_id', $hotelId)
                                                            ->pluck('room_type_id'))
                                                        ->pluck('name', 'id');
                                                })
                                                ->helperText(fn($get) => $get('../../hotel_id')
                                                    ? null
                                                    : __('Select a hotel first'))
                                                ->required()
                                                ->reactive(),

                                            Components\TextInput::make('amount_uz')
                                                ->numeric()
                                                ->default(0)
                                                ->label(__('UZ')),

                                            Components\TextInput::make('amount_foreign')
                                                ->numeric()
                                                ->default(0)
                                                ->label(__('Foreign')),
                                        ])
                                    ])
                            
                            ])->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),
                            
                            // Transport
                            Components\Section::make(__('Transport info'))
                                ->icon('heroicon-o-truck')
                                ->schema([

                                Components\Grid::make(4)->schema([
                                    Components\DateTimePicker::make('date')
                                        ->label(__('Date & Time'))
                                        ->displayFormat('d.m.Y H:i')
                                        ->seconds(false),
                                    Components\Select::make('transport_class_id')
                                        ->label(__('Transport class'))
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(fn() => TourService::getTransportClasses())
                                        ->reactive()
                                        ->afterStateUpdated(function($state, $set) {
                                            $set('route_id', null);
                                            $set('price', null);
                                        }),
                                    Components\Select::make('route_id')
                                        ->label(__('Route'))
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(fn($get) => $get('transport_class_id')
                                            ? TourService::getRoutesForTransportClass((int)$get('transport_class_id'))
                                            : []
                                        )
                                        ->reactive()
                                        ->afterStateUpdated(function($state, $get, $set) {
                                            if ($state && $get('transport_class_id')) {
                                                $price = TourService::getRoutePriceForTransportClass(
                                                    (int)$state,
                                                    (int)$get('transport_class_id')
                                                );
                                                if ($price !== null) {
                                                    $set('price', $price);
                                                    $set('price_currency', 'USD');
                                                }
                                            }
                                        }),
                                ]),

                                Components\Grid::make(3)->schema([
                                    Components\TextInput::make('transport_route')
                                        ->label(__('Destination'))
                                        ->required(),

                                    Components\Select::make('status')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->required()
                                        ->label(__('Status')),

                                    self::getExpensePriceInput(),
                                ]),

                                Components\Textarea::make('comment')
                                    ->label(__('Comment'))
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),
                            
                            // Train
                            Components\Section::make(__('Train info'))
                                ->icon('heroicon-o-ticket')
                                ->schema([

                                Components\Grid::make(4)->schema([
                                    Components\Select::make('train_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Train'))
                                        ->options(TourService::getTrains()),
                                    
                                    Components\Select::make('to_city_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('City to'))
                                        ->options(TourService::getCities())
                                        ->reactive(),
                                    
                                    Components\TimePicker::make('departure_time')
                                        ->seconds(false)
                                        ->label(__('Departure time')),
                                    
                                    Components\DateTimePicker::make('arrival_time')
                                        ->seconds(false)
                                        ->label(__('Arrival time')),
                                ]),
                                
                                Components\Grid::make(4)->schema([
                                    Components\TextInput::make('train_class_second')
                                        ->label(__('Second'))
                                        ->numeric(),
                                    Components\TextInput::make('train_class_business')
                                        ->label(__('Business'))
                                        ->numeric(),
                                    Components\TextInput::make('train_class_vip')
                                        ->label(__('VIP'))
                                        ->numeric(),
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->formatStateUsing(
                                            fn($state, $get) => $get('id') ? $get(
                                                'status'
                                            ) : ExpenseStatus::Confirmed->value
                                        )
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Status')),
                                ]),

                                Components\Grid::make(4)->schema([
                                    Components\TextInput::make('train_service_fee')
                                        ->label(__('Service Fee'))
                                        ->numeric(),
                                ]),

                                Components\Textarea::make('comment')
                                    ->label(__('Comment'))
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Train->value),
                            
                            // Flight
                            Components\Section::make(__('Flight info'))
                                ->icon('heroicon-o-paper-airplane')
                                ->schema([

                                Components\Grid::make(4)->schema([
                                    self::getExpensePriceInput(),
                                    
                                    Components\TextInput::make('plane_route'),
                                    
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Status')),
                                    
                                    Components\Textarea::make('comment')
                                        ->label(__('Comment')),
                                ]),
                                
                                Components\Grid::make(4)->schema([
                                    Components\TimePicker::make('departure_time')
                                        ->seconds(false)
                                        ->label(__('Departure time')),
                                    
                                    Components\TextInput::make('departure_number')
                                        ->label(__('Departure reys number')),
                                    
                                    Components\DateTimePicker::make('arrival_time')
                                        ->seconds(false)
                                        ->label(__('Arrival time')),
                                    
                                    Components\TextInput::make('arrival_number')
                                        ->label(__('Arrival reys number')),
                                ]),
                                
                                Components\Grid::make(4)->schema([
                                    Components\Select::make('plane_type')
                                        ->options(PlaneType::class)
                                        ->label(__('Plane type')),
                                    
                                    Components\TextInput::make('plane_service_fee')
                                        ->label(__('Service fee')),
                                ]),
                            
                            ])->visible(fn($get) => $get('type') == ExpenseType::Flight->value),
                            
                            // Extra
                            Components\Section::make(__('Extra info'))
                                ->icon('heroicon-o-plus-circle')
                                ->schema([
                                Components\Grid::make(3)->schema([
                                    Components\TextInput::make('other_name')
                                        ->label(__('Name')),
                                    
                                    self::getExpensePriceInput(),
                                    
                                    Components\Textarea::make('comment')->label(__('Comment')),
                                ]),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Extra->value),
                            
                            // Conference
                            Components\Section::make(__('Conference info'))
                                ->icon('heroicon-o-user-group')
                                ->schema([

                                Components\Grid::make(4)->schema([
                                    Components\TextInput::make('conference_name')
                                        ->label(__('Conference name')),
                                    
                                    self::getExpensePriceInput(),
                                    
                                    Components\TextInput::make('coffee_break')
                                        ->suffix('%')
                                        ->label(__('Coffee break')),
                                    
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Status')),
                                ]),
                                
                                Components\Textarea::make('comment')
                                    ->label(__('Comment'))
                                    ->columnSpanFull(),
                            
                            ])->visible(fn($get) => $get('type') == ExpenseType::Conference->value),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(fn(array $data) => self::syncHotelTimes($data))
                        ->mutateRelationshipDataBeforeSaveUsing(fn(array $data) => self::syncHotelTimes($data))
                    /*->mutateRelationshipDataBeforeCreateUsing(function ($data, $get) {
                        $tourData = $get('../../');
                        $groups = collect($tourData['groups'] ?? []);
                        $passengersCount = $groups->sum(fn($group) => count($group['passengers'] ?? []));
                        return ExpenseService::mutateExpense(
                            data: $data,
                            totalPax: $passengersCount,
                            roomAmounts: ExpenseService::getRoomingAmountsForExpense($data),
                            companyId: $tourData['company_id'],
                            isTps: false
                        );
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function ($data, $get) {
                        $tourData = $get('../../');
                        $groups = collect($tourData['groups'] ?? []);
                        $passengersCount = $groups->sum(fn($group) => count($group['passengers'] ?? []));
                        return ExpenseService::mutateExpense(
                            data: $data,
                            totalPax: $passengersCount,
                            roomAmounts: ExpenseService::getRoomingAmountsForExpense($data),
                            companyId: $tourData['company_id'],
                            isTps: false
                        );
                    })*/
                ]),
        ])->disabled(function($record) {
            if (!$record) {
                return false;
            }
            
            if (auth()->user()->isOperator()) {
                return $record->created_by != auth()->id();
            }
            return false;
        });
    }
    
    public static function getExpensePriceInput(string $label = 'Price'): Components\TextInput
    {
        return Components\TextInput::make('price')
            ->label(fn($get) => __($label) . ' (' . ($get('price_currency') ?? 'UZS') . ')')
            ->suffixAction(
                Components\Actions\Action::make('toggle-currency')
                    ->icon('heroicon-o-banknotes')
                    ->iconSize('md')
                    ->action(function($get, $set) {
                        $set('price_currency', $get('price_currency') != 'USD' ? 'USD' : 'UZS');
                    })
            )
            ->numeric();
    }

    /**
     * Keep the legacy hotel_checkin_time / hotel_checkout_time string columns
     * (still read by ExportHotelService and CompanyExpense exports) in sync
     * with the real date / hotel_checkout_date_time columns.
     */
    private static function syncHotelTimes(array $data): array
    {
        if (($data['type'] ?? null) != ExpenseType::Hotel->value) {
            return $data;
        }

        $data['hotel_checkin_time'] = $data['date']
            ? Carbon::parse($data['date'])->format('H:i')
            : null;

        $data['hotel_checkout_time'] = ($data['hotel_checkout_date_time'] ?? null)
            ? Carbon::parse($data['hotel_checkout_date_time'])->format('H:i')
            : null;

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->filters([
                Tables\Filters\Filter::make('country_id')
                    ->columnSpanFull()
                    ->form([
                        Components\Grid::make(['sm' => 2, 'md' => 3])->schema([
                            Components\Checkbox::make('active')
                                ->label(__('Active'))
                                ->default(false)
                                ->inline(false),
                            Components\Checkbox::make('archive')
                                ->label(__('Archive'))
                                ->default(false)
                                ->inline(false),
                            Components\Select::make('year')
                                ->label(__('Year'))
                                ->native(false)
                                ->default((int)date('Y'))
                                ->options(function() {
                                    $currentYear = (int)date('Y');
                                    $startYear = $currentYear - 5;
                                    $endYear = $currentYear;
                                    return array_combine(
                                        $yearsArray = range($startYear, $endYear),
                                        $yearsArray
                                    );
                                }),
                        ]),
                        Components\Grid::make(['sm' => 2, 'md' => 4])->schema([
                            Components\Select::make('company_id')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(Company::query()->pluck('name', 'id')->toArray()),
                            Components\Select::make('created_by')
                                ->label(__('Admin creator'))
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(User::query()->pluck('name', 'id')->toArray()),
                            
                            Components\DatePicker::make('created_from')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                            Components\DatePicker::make('created_until')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                        ])
                    ])
                    ->query(function(Builder $query, $data) {
                        if ($data['active'] && $data['archive']) {
                            $query->where(function($query) use ($data) {
                                $query->where('end_date', '>=', Carbon::now())
                                    ->orWhere('end_date', '<', Carbon::now());
                            });
                        } elseif ($data['active']) {
                            $query->where('end_date', '>=', Carbon::now());
                        } elseif ($data['archive']) {
                            $query->where('end_date', '<', Carbon::now());
                        }
                        
                        return $query
                            ->when($data['year'], function ($query, $year) {
                                return $query->where(function($q) use ($year) {
                                    $q->whereYear('created_at', $year);
                                });
                            })
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
                                fn($query, $createdFrom) => $query->whereDate('start_date', '>=', $createdFrom)
                            )
                            ->when(
                                $data['created_until'],
                                fn($query, $createdUntil) => $query->whereDate('start_date', '<=', $createdUntil)
                            );
                    })
                    ->indicateUsing(function(array $data): array {
                        $query = Tour::query()->where('type', TourType::TPS->value);
                        
                        $indicators = [];
                        if ($data['active'] && $data['archive']) {
                            $query = $query
                                ->where('start_date', '>=', Carbon::now())
                                ->orWhere('start_date', '<', Carbon::now());
                            $indicators['active'] = "Active & Archive ({$query->count()})";
                        }
                        
                        if ($data['active']) {
                            $query = $query->where('start_date', '>=', Carbon::now());
                            $indicators['active'] = "Active ({$query->count()})";
                        }
                        if ($data['archive']) {
                            $query = $query->where('start_date', '<', Carbon::now());
                            $indicators['archive'] = "Archive ({$query->count()})";
                        }
                        if ($data['country_id'] ?? null) {
                            $indicators['country_id'] = 'Country: ' . Country::find($data['country_id'])->name;
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
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->columns([
                Columns\TextColumn::make('group_number')
                    ->searchable(),
                
                Columns\TextColumn::make('company.name')
                    ->numeric()
                    ->sortable()
                    ->searchable(),
                
                Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                
                Columns\TextColumn::make('expenses_total')
                    ->badge(fn(Tour $record) => TourService::isVisible($record))
                    ->color('danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->formatStateUsing(function($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
                        }
                        
                        return '-';
                    })
                    ->sortable()
                    ->searchable(),
                
                Columns\TextColumn::make('start_date')
                    ->label(__('Start'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Columns\TextColumn::make('end_date')
                    ->label(__('End'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Columns\TextColumn::make('requested_by')
                    ->searchable(),

                Columns\TextColumn::make('createdBy.name')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(null)
            ->recordAction(StatusAction::class)
            ->actions([
                Tables\Actions\Action::make('export_all')
                    ->label(__('Reports'))
                    ->icon('heroicon-o-document-text')
                    ->url(fn(Tour $record) => route('export-all', $record)),
                Tables\Actions\EditAction::make(),
                StatusAction::make()->label(__(''))->icon(''),
            ], position: Tables\Enums\ActionsPosition::BeforeColumns)
            ->headerActions([
            
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
