<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Hotel;
use App\Enums\RateEnum;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Enums\RoomSeasonType;
use App\Services\TourService;
use Filament\Resources\Resource;
use App\Tables\Columns\PeriodsColumn;
use App\Filament\Resources\HotelResource\Pages;
use Illuminate\Database\Eloquent\Model;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use App\Filament\Resources\TourTpsResource\Actions\StatusAction;
use App\Filament\Resources\HotelResource\Actions\HotelPeriodsAction;
use App\Filament\Resources\HotelResource\RelationManagers\RoomTypesRelationManager;

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
            'Email' => $record->email,
            'Address' => $record->address,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->suffixAction(function($record) {
                            if (!$record?->email) {
                                return [];
                            }
                            return [
                                Forms\Components\Actions\Action::make('hotel_email')
                                    ->icon('heroicon-o-paper-airplane')
                                    ->url("mailto:{$record->email}", true)
                            ];
                        }),

                    Forms\Components\TextInput::make('inn'),

                    Forms\Components\TextInput::make('booking_cancellation_days')->numeric(),
                ]),
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\Select::make('country_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->relationship('country', 'name')
                        ->afterStateUpdated(fn($get, $set) => $set('city_id', null))
                        ->reactive(),

                    Forms\Components\Select::make('city_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->relationship('city', 'name')
                        ->options(fn($get) => TourService::getCities($get('country_id'))),

                    Forms\Components\TextInput::make('contract_number')->maxLength(255),
                    Forms\Components\DatePicker::make('contract_date')->native(false),
                ]),
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('company_name')->maxLength(255),
                    Forms\Components\TextInput::make('address')->maxLength(255),
                    Forms\Components\Repeater::make('phones')
                        ->relationship('phones')
                        ->addActionLabel('Add phone')
                        ->simple(
                            PhoneInput::make('phone_number')
                                ->strictMode()
                                ->onlyCountries(['UZ'])
                                ->defaultCountry('UZ')
                                ->suffixAction(function($record) {
                                    if (!$record?->phone_number) {
                                        return [];
                                    }
                                    return [
                                        Forms\Components\Actions\Action::make('hotel_phone')
                                            ->icon('heroicon-o-paper-airplane')
                                            ->url("https://t.me/{$record->phone}", true)
                                    ];
                                })
                                ->required(),
                        ),

                    Forms\Components\Select::make('rate')
                        ->options(function() {
                            $options = [];
                            foreach (RateEnum::cases() as $rate) {
                                $options[$rate->value] = $rate->getLabel();
                            }
                            return $options;
                        }),

                    /*PhoneInput::make('phone')
                        ->suffixAction(function ($record) {
                            if (!$record?->phone) {
                                return [];
                            }
                            return [
                                Forms\Components\Actions\Action::make('hotel_phone')
                                    ->icon('heroicon-o-paper-airplane')
                                    ->url("https://t.me/{$record->phone}", true)
                            ];
                        }),*/
                ]),

                Forms\Components\Grid::make()->schema([
                    Forms\Components\Textarea::make('comment')
                        ->columnSpan(1)
                        ->maxLength(255),
                ]),

                Forms\Components\Repeater::make('periods')
                    ->grid(2)
                    ->extraAttributes(['class' => 'repeater-guides'])
                    ->relationship('periods')
                    ->columnSpanFull()
                    ->addActionAlignment('end')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('start_date')
                                ->native(false)
                                ->required(),
                            Forms\Components\DatePicker::make('end_date')
                                ->native(false)
                                ->minDate(fn($get) => $get('start_date'))
                                ->required(),
                            Forms\Components\Select::make('season_type')
                                ->options(RoomSeasonType::class)
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                PeriodsColumn::make('room_prices')
                    ->label('Room prices')
                    ->getStateUsing(fn($record, $livewire) => [
                        'hotel' => $record,
                        'isFirst' => $record->is($livewire->getTableRecords()->first()),
                    ]),

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
                    ->getStateUsing(function($record) {
                        return $record->phones->map(function($phone) {
                            return "<a href='https://t.me/{$phone->phone_number}' target='_blank'>{$phone->phone_number}</a>";
                        })->implode('<br/>');
                    })
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
            ->filters([
                //
            ])
            ->recordUrl(null)
            //            ->recordAction(HotelPeriodsAction::class)
            ->actions([
                Tables\Actions\EditAction::make(),
                //                HotelPeriodsAction::make()->label('')->icon(''),
            ], position: Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RoomTypesRelationManager::class,
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
