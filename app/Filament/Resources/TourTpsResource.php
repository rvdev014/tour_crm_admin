<?php

namespace App\Filament\Resources;

use App\Models\Company;
use App\Enums\ExpenseType;
use App\Enums\TicketType;
use App\Enums\TransportStatus;
use App\Filament\Resources\TourTpsResource\Pages;
use App\Filament\Resources\TourTpsResource\RelationManagers;
use App\Models\HotelRoomType;
use App\Models\Tour;
use App\Models\Transport;
use Filament\Forms;
use Filament\Forms\Components;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TourTpsResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Tours TPS';
    protected static ?string $slug = 'tour-tps';
    protected static ?int $navigationSort = 1;

    protected static function priceForCompany($companyId, $hotelRoomTypeId)
    {
        if (!$companyId || !$hotelRoomTypeId) {
            return 0;
        }

        $company = Company::find($companyId);
        $hotelRoomType = HotelRoomType::find($hotelRoomTypeId);
        if ($company && $hotelRoomType) {
            return $hotelRoomType->price + ($hotelRoomType->price * $company->additional_percent / 100);
        }

        return 0;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Section::make('Tour details')->schema([
                    Components\Grid::make()->schema([
                        Components\Select::make('company_id')
                            ->relationship('company', 'name')
                            ->reactive()
                            ->required(),
                        Components\DatePicker::make('start_date')
                            ->required(),
                        Components\DatePicker::make('end_date')
                            ->required(),
                        Components\Select::make('country_id')
                            ->relationship('country', 'name')
                            ->required(),
                        Components\TextInput::make('pax')
                            ->required()
                            ->numeric(),
                        Components\TextInput::make('price')
                            ->required()
                            ->numeric(),
                    ]),
                ]),

                Components\Section::make('Tour details')->schema([
                    Components\Repeater::make('days')
                        ->relationship('days')
                        ->schema([
                            Components\Grid::make()->schema([
                                Components\DatePicker::make('date')
                                    ->required(),
                                Components\Select::make('city_id')
                                    ->relationship('city', 'name')
                                    ->required(),
                                Components\Grid::make(1)
                                    ->schema([
                                        Components\Repeater::make('expenses')
                                            ->relationship('expenses')
                                            ->schema([
                                                Components\Grid::make()->schema([
                                                    Components\Select::make('type')
                                                        ->label('Expense Type')
                                                        ->options(ExpenseType::class)
                                                        ->required()
                                                        ->reactive(),

                                                    Components\TextInput::make('price')
                                                        ->label('Price')
                                                        ->numeric()
                                                        ->required(),

                                                    Components\Textarea::make('comment')
                                                        ->label('Comment')
                                                        ->nullable()
                                                        ->columnSpanFull(),
                                                ]),

                                                // Transport
                                                Components\Grid::make(3)->schema([
                                                    Components\Select::make('driver_employee_id')
                                                        ->label('Driver')
                                                        ->relationship('driverEmployee', 'name')
                                                        ->nullable()
                                                        ->visible(
                                                            fn($get) => $get('type') == ExpenseType::Transport->value
                                                        ),
                                                    Components\Select::make('car_ids')
                                                        ->label('Car IDs')
                                                        ->options(function () {
                                                            return Transport::all()->pluck('name', 'id')->toArray();
                                                        })
                                                        ->multiple()
                                                        ->getOptionLabelFromRecordUsing(fn (Model $record) => dd($record))
                                                        ->nullable()
                                                        ->visible(
                                                            fn($get) => $get('type') == ExpenseType::Transport->value
                                                        ),
                                                    Components\Select::make('transport_status')
                                                        ->label('Transport Status')
                                                        ->options(TransportStatus::class)
                                                        ->nullable()
                                                        ->visible(
                                                            fn($get) => $get('type') == ExpenseType::Transport->value
                                                        ),
                                                ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                                                Components\Grid::make(3)->schema([
                                                    Components\TextInput::make('num_people')
                                                        ->label('Number of People')
                                                        ->numeric()
                                                        ->nullable()
                                                        ->visible(
                                                            fn($get) => $get('type') == ExpenseType::Transport->value
                                                        ),
                                                    Components\TimePicker::make('transport_time')
                                                        ->label('Transport Time')
                                                        ->nullable()
                                                        ->visible(
                                                            fn($get) => $get('type') == ExpenseType::Transport->value
                                                        ),
                                                    Components\TextInput::make('location')
                                                        ->label('Location')
                                                        ->nullable()
                                                        ->visible(
                                                            fn($get) => $get('type') == ExpenseType::Transport->value
                                                        ),
                                                ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                                                Components\Grid::make(3)->schema([
                                                    Components\TextInput::make('transport_route')
                                                        ->label('Transport Route')
                                                        ->nullable()
                                                        ->visible(
                                                            fn($get) => $get('type') == ExpenseType::Transport->value
                                                        ),
                                                ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),
                                                Components\Placeholder::make('transport_total')
                                                    ->label('Transport Total')
                                                    ->visible(
                                                        fn($get) => $get('type') == ExpenseType::Transport->value
                                                    ),

                                                // Ticket
                                                Components\Grid::make(3)->schema([
                                                    Components\Select::make('ticket_type')
                                                        ->label('Ticket Type')
                                                        ->options(TicketType::class)
                                                        ->nullable()
                                                        ->visible(fn($get) => $get('type') == ExpenseType::Ticket->value
                                                        ),
                                                    Components\TimePicker::make('ticket_time')
                                                        ->label('Ticket Time')
                                                        ->nullable()
                                                        ->visible(fn($get) => $get('type') == ExpenseType::Ticket->value
                                                        ),
                                                    Components\TextInput::make('ticket_route')
                                                        ->label('Ticket Route')
                                                        ->nullable()
                                                        ->visible(fn($get) => $get('type') == ExpenseType::Ticket->value
                                                        ),
                                                ])->visible(fn($get) => $get('type') == ExpenseType::Ticket->value),

                                                // Hotel
                                                Components\Grid::make()->schema([
                                                    Components\Select::make('hotel_id')
                                                        ->label('Hotel')
                                                        ->relationship('hotel', 'name')
                                                        ->nullable()
                                                        ->reactive()
                                                        ->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),
                                                    Components\Select::make('hotel_room_type_id')
                                                        ->label('Hotel Room Type')
                                                        ->options(function ($get) {
                                                            $hotelId = $get('hotel_id');
                                                            if (!empty($hotelId)) {
                                                                $result = [];
                                                                $roomTypes = HotelRoomType::where('hotel_id', $hotelId)->orderBy('room_type')->get();
                                                                foreach ($roomTypes as $roomType) {
                                                                    $result[$roomType->id] = "{$roomType->room_type->getLabel()} {$roomType->price}";
                                                                }

                                                                return $result;
                                                            }

                                                            return [];
                                                        })
                                                        ->nullable()
                                                        ->reactive()
                                                        ->afterStateUpdated(function ($get, $set) {
                                                            $price = self::priceForCompany($get('../../../../company_id'), $get('hotel_room_type_id'));
                                                            if ($price > 0) {
                                                                $set('price', $price);
                                                            }
                                                        })
                                                        ->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),
                                                ])->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),

                                                // Guide
                                                Components\Select::make('guide_employee_id')
                                                    ->label('Guide')
                                                    ->relationship(
                                                        'guideEmployee',
                                                        'name'
                                                    ) // Assuming guide relation exists
                                                    ->nullable()
                                                    ->visible(fn($get) => $get('type') == ExpenseType::Guide->value),
                                            ])
                                    ])
                            ]),
                        ])
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('company.name')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('group_number')
                    ->searchable(),
                Columns\TextColumn::make('pax')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('status')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('price')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('expenses')
                    ->numeric()
                    ->badge()
                    ->color('danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->sortable(),
                Columns\TextColumn::make('income')
                    ->numeric()
                    ->badge()
                    ->color(fn (Tour $record) => $record->income > 0 ? 'success' : 'danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
