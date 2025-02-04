<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseStatus;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use App\Filament\Resources\TransferResource\Pages;
use App\Filament\Resources\TransferResource\RelationManagers;
use App\Models\Transfer;
use App\Services\TourService;
use Filament\Forms\Components;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('from_city_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label('City from')
                        ->relationship('fromCity', 'name')
                        ->options(fn() => TourService::getCities(null, isAll: true))
                        ->reactive(),

                    Forms\Components\Select::make('to_city_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label('City to')
                        ->relationship('toCity', 'name')
                        ->options(function ($get) {
                            $fromCityId = $get('from_city_id');
                            if (!empty($fromCityId)) {
                                $cities = TourService::getCities(null, false, true);
                                return $cities->filter(fn($city) => $city->id != $fromCityId)->pluck('name', 'id');
                            }

                            return [];
                        })
                        ->preload()
                        ->reactive(),

                    Forms\Components\Select::make('company_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label('Company')
                        ->relationship('company', 'name')
                        ->required(),
                ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('group_number')
                        ->label('Group number'),

                    Forms\Components\TextInput::make('pax')
                        ->label('Pax')
                        ->numeric(),

                    Forms\Components\Select::make('status')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(ExpenseStatus::class)
                        ->label('Status'),
                ]),

                Forms\Components\Grid::make(3)->schema([
//                    Forms\Components\TextInput::make('driver')
//                        ->label('Driver'),
                    Components\Select::make('driver_id')
                        ->options(TourService::getDrivers())
                        ->native(false)
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('transport_type')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label('Transport type')
                        ->options(TransportType::class)
                        ->reactive()
                        ->afterStateUpdated(function ($get, $set) {
                            $price = TourService::getTransportPrice(
                                $get('transport_type'),
                                $get('transport_comfort_level'),
                            );
                            $set('price', $price);
                        }),

                    Forms\Components\Select::make('transport_comfort_level')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label('Comfort level')
                        ->options(TransportComfortLevel::class)
                        ->reactive()
                        ->afterStateUpdated(function ($get, $set) {
                            $price = TourService::getTransportPrice(
                                $get('transport_type'),
                                $get('transport_comfort_level'),
                            );
                            $set('price', $price);
                        }),
                ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('place_of_submission')
                        ->label('Pick up Location'),

                    Forms\Components\DateTimePicker::make('date_time')
                        ->native(false)
                        ->seconds(false),

                    Forms\Components\TextInput::make('price')
                        ->numeric(),
                ]),

                Forms\Components\Textarea::make('comment')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->modifyQueryUsing(fn($query) => $query->with(['fromCity', 'toCity', 'company']))
            ->filtersFormColumns(3)
            ->filters([
//                TernaryFilter::make('verified')
//                    ->nullable(),
                Tables\Filters\Filter::make('today')
                    ->form([
                        Components\Grid::make()->schema([
                            Components\Checkbox::make('today')
                                ->label('Today')
                                ->default(false),
                            Components\Checkbox::make('tomorrow')
                                ->label('Tomorrow')
                                ->default(false),
                        ]),
                    ])
                    ->query(function (Builder $query, $data) {
                        if ($data['today'] && $data['tomorrow']) {
                            $today = Carbon::today();
                            $tomorrow = Carbon::tomorrow();
                            return $query->whereDate('date_time', $today)
                                ->orWhereDate('date_time', $tomorrow);
                        }

                        if ($data['today']) {
                            $today = Carbon::today();
                            return $query->whereDate('date_time', $today);
                        }

                        if ($data['tomorrow']) {
                            $tomorrow = Carbon::tomorrow();
                            return $query->whereDate('date_time', $tomorrow);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['today'] && $data['tomorrow']) {
                            $indicators['today'] = 'Today & Tomorrow';
                            return $indicators;
                        }

                        if ($data['today']) {
                            $indicators['today'] = 'Today';
                        }
                        if ($data['tomorrow']) {
                            $indicators['tomorrow'] = 'Tomorrow';
                        }

                        return $indicators;
                    }),
                Tables\Filters\Filter::make('status')
                    ->form([
                        Components\Select::make('status')
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->options(ExpenseStatus::class)
                    ])
                    ->query(function (Builder $query, $data) {
                        return $query
                            ->when(
                                $data['status'],
                                fn($query, $status) => $query->where('status', $status)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['status'] ?? null) {
                            $indicators['status'] = 'Status: ' . ExpenseStatus::from($data['status'])->getLabel();
                        }

                        return $indicators;
                    }),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Components\Grid::make()->schema([
                            Components\DatePicker::make('date_from')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                            Components\DatePicker::make('date_until')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                        ])
                    ])
                    ->query(function (Builder $query, $data) {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn($query, $dateFrom) => $query->whereDate('date_time', '>=', $dateFrom)
                            )
                            ->when(
                                $data['date_until'],
                                fn($query, $dateUntil) => $query->whereDate('date_time', '<=', $dateUntil)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date_from'] ?? null) {
                            $indicators['date_from'] = 'Order from ' . Carbon::parse(
                                    $data['date_from']
                                )->toFormattedDateString();
                        }
                        if ($data['date_until'] ?? null) {
                            $indicators['date_until'] = 'Order until ' . Carbon::parse(
                                    $data['date_until']
                                )->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('date_time', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('company.name'),

                Tables\Columns\TextColumn::make('date_time')
                    ->label('Date & Time')
                    ->dateTime()
                    ->formatStateUsing(fn($state) => $state->format('d.m.Y H:i'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('place_of_submission')
                    ->label('Pick up Location'),

                Tables\Columns\TextColumn::make('pax')
                    ->formatStateUsing(function ($record, $state) {
                        return $state . ' pax';
                    })
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fromCity.name')
                    ->label('Route')
                    ->formatStateUsing(function ($record, $state) {
                        return $state . ' - ' . $record->toCity?->name;
                    }),

                Tables\Columns\TextColumn::make('driver.name'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('transport_type')->sortable(),

                Tables\Columns\TextColumn::make('transport_comfort_level')->sortable(),

                Tables\Columns\TextColumn::make('group_number')->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                //                Tables\Columns\TextColumn::make('updated_at')
                //                    ->dateTime()
                //                    ->sortable()
                //                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListTransfers::route('/'),
            'create' => Pages\CreateTransfer::route('/create'),
            'edit' => Pages\EditTransfer::route('/{record}/edit'),
        ];
    }
}
