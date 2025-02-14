<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseStatus;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use App\Filament\Resources\TransferResource\Pages;
use App\Filament\Resources\TransferResource\RelationManagers;
use App\Models\Transfer;
use App\Services\TourService;
use Closure;
use Filament\Forms\Components;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 3;

    public static function canEdit(Model $record): bool
    {
        if ($record->status == ExpenseStatus::Done) {
            return false;
        }
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    /*Forms\Components\Select::make('from_city_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label('City from')
                        ->relationship('fromCity', 'name')
                        ->options(fn() => TourService::getCities(null, isAll: true))
                        ->reactive(),*/

                    Forms\Components\Select::make('to_city_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label('City')
                        ->relationship('toCity', 'name')
                        /*->options(function ($get) {
                            $fromCityId = $get('from_city_id');
                            if (!empty($fromCityId)) {
                                $cities = TourService::getCities(null, false, true);
                                return $cities->filter(fn($city) => $city->id != $fromCityId)->pluck('name', 'id');
                            }

                            return [];
                        })*/
                        ->options(TourService::getCities())
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
                    /*->afterStateUpdated(function ($get, $set) {
                        $price = TourService::getTransportPrice(
                            $get('transport_type'),
                            $get('transport_comfort_level'),
                        );
                        $set('price', $price);
                    })*/,

//                    Forms\Components\Select::make('transport_comfort_level')
//                        ->native(false)
//                        ->searchable()
//                        ->preload()
//                        ->label('Comfort level')
//                        ->options(TransportComfortLevel::class)
//                        ->reactive()
//                        ->afterStateUpdated(function ($get, $set) {
//                            $price = TourService::getTransportPrice(
//                                $get('transport_type'),
//                                $get('transport_comfort_level'),
//                            );
//                            $set('price', $price);
//                        }),
                ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('place_of_submission')
                        ->label('Pick up Location'),

                    Forms\Components\DateTimePicker::make('date_time')
                        ->displayFormat('d.m.Y H:i')
                        ->minDate(now())
                        ->native(false)
                        ->seconds(false),

                    Forms\Components\TextInput::make('price')
                        ->numeric(),
                ]),

                Forms\Components\Textarea::make('comment')
                    ->columnSpanFull(),
            ]);
    }

    protected function getTableRecordClassUsing(): ?Closure
    {
        return function (Transfer $record) {
            return match ($record->status) {
                'draft' => 'opacity-30',
                'reviewing' => [
                    'border-l-solid',
                    'border-l-2',
                    'border-l-orange-600',
                    'dark:border-l-orange-300' => config('filament.dark_mode'),
                    'opacity-30',
                ],
                'published' => 'border-0 border-l-solid border-l-2 border-l-orange-400',
                default => null,
            };
            return null;
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->modifyQueryUsing(fn($query) => $query->with(['toCity', 'company']))
            ->filtersFormColumns(3)
            ->recordClasses(function($record) {
                if ($record->status == ExpenseStatus::Done) {
                    return ' color-green';
                }

                return match ($record->status) {
                    ExpenseStatus::Done => 'color-green',
                    ExpenseStatus::Rejected => 'color-red',
                    ExpenseStatus::Confirmed => 'color-light-orange',
                    default => null,
                };
            })
            ->filters([
//                TernaryFilter::make('verified')
//                    ->nullable(),
                Tables\Filters\Filter::make('today')
                    ->columnSpanFull()
                    ->form([
                        Components\Grid::make(5)->schema([
                            Components\Checkbox::make('today')
                                ->label('Today')
                                ->default(false),
                            Components\Checkbox::make('tomorrow')
                                ->label('Tomorrow')
                                ->default(false),
                            Components\Select::make('status')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(ExpenseStatus::class),
                            Components\DatePicker::make('date_from')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                            Components\DatePicker::make('date_until')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                        ])
                    ])
                    ->query(function (Builder $query, $data) {
                        if ($data['today'] && $data['tomorrow']) {
                            $query = $query
                                ->whereDate('date_time', Carbon::today())
                                ->orWhereDate('date_time', Carbon::tomorrow());
                        }
                        if ($data['today']) {
                            $query = $query->whereDate('date_time', Carbon::today());
                        }
                        if ($data['tomorrow']) {
                            $tomorrow = Carbon::tomorrow();
                            $query = $query->whereDate('date_time', $tomorrow);
                        }
                        if ($data['status']) {
                            $query = $query->where('status', $data['status']);
                        }
                        if ($data['date_from']) {
                            $query = $query->whereDate('date_time', '>=', $data['date_from']);
                        }
                        if ($data['date_until']) {
                            $query = $query->whereDate('date_time', '<=', $data['date_until']);
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $query = Transfer::query();

                        $indicators = [];
                        if ($data['today'] && $data['tomorrow']) {
                            $query = $query
                                ->whereDate('date_time', Carbon::today())
                                ->orWhereDate('date_time', Carbon::tomorrow());
                            $indicators['today'] = "Today & Tomorrow ({$query->count()})";
                        }

                        if ($data['today']) {
                            $query = $query->whereDate('date_time', Carbon::today());
                            $indicators['today'] = "Today ({$query->count()})";
                        }
                        if ($data['tomorrow']) {
                            $query = $query->whereDate('date_time', Carbon::tomorrow());
                            $indicators['tomorrow'] = "Tomorrow ({$query->count()})";
                        }
                        if ($data['status']) {
                            $query = $query->where('status', $data['status']);
                            $indicators['status'] = 'Status: ' . ExpenseStatus::from($data['status'])->getLabel(
                                ) . " ({$query->count()})";
                        }
                        if ($data['date_from']) {
                            $indicators['date_from'] = 'Order from ' . Carbon::parse(
                                    $data['date_from']
                                )->toFormattedDateString();
                        }
                        if ($data['date_until']) {
                            $indicators['date_until'] = 'Order until ' . Carbon::parse(
                                    $data['date_until']
                                )->toFormattedDateString();
                        }

                        return $indicators;
                    }),
//                Tables\Filters\Filter::make('status')
//                    ->form([
//                        Components\Select::make('status')
//                            ->native(false)
//                            ->searchable()
//                            ->preload()
//                            ->options(ExpenseStatus::class)
//                    ])
//                    ->query(function (Builder $query, $data) {
//                        return $query
//                            ->when(
//                                $data['status'],
//                                fn($query, $status) => $query->where('status', $status)
//                            );
//                    })
//                    ->indicateUsing(function (array $data): array {
//                        $indicators = [];
//                        if ($data['status'] ?? null) {
//                            $indicators['status'] = 'Status: ' . ExpenseStatus::from($data['status'])->getLabel();
//                        }
//
//                        return $indicators;
//                    }),
//                Tables\Filters\Filter::make('date')
//                    ->form([
//                        Components\Grid::make()->schema([
//                            Components\DatePicker::make('date_from')
//                                ->displayFormat('d.m.Y')
//                                ->native(false),
//                            Components\DatePicker::make('date_until')
//                                ->displayFormat('d.m.Y')
//                                ->native(false),
//                        ])
//                    ])
//                    ->query(function (Builder $query, $data) {
//                        return $query
//                            ->when(
//                                $data['date_from'],
//                                fn($query, $dateFrom) => $query->whereDate('date_time', '>=', $dateFrom)
//                            )
//                            ->when(
//                                $data['date_until'],
//                                fn($query, $dateUntil) => $query->whereDate('date_time', '<=', $dateUntil)
//                            );
//                    })
//                    ->indicateUsing(function (array $data): array {
//                        $indicators = [];
//                        if ($data['date_from'] ?? null) {
//                            $indicators['date_from'] = 'Order from ' . Carbon::parse(
//                                    $data['date_from']
//                                )->toFormattedDateString();
//                        }
//                        if ($data['date_until'] ?? null) {
//                            $indicators['date_until'] = 'Order until ' . Carbon::parse(
//                                    $data['date_until']
//                                )->toFormattedDateString();
//                        }
//
//                        return $indicators;
//                    }),
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('date_time', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('company.name'),

                Tables\Columns\TextColumn::make('date_time')
                    ->label('Date & Time')
                    ->dateTime()
                    ->formatStateUsing(function ($state) {
                        return <<<HTML
<div style="text-align: center">
    <p>{$state->format('d.m.Y')}</p>
    <p>{$state->format('H:i')}</p>
</div>
HTML;
                    })
                    ->html()
                    ->sortable(),

                Tables\Columns\TextColumn::make('place_of_submission')
                    ->label('Pick up Location'),

                Tables\Columns\TextColumn::make('pax')
                    ->formatStateUsing(function ($record, $state) {
                        return $state . ' pax';
                    })
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('toCity.name')
                    ->label('Route')
                /*->formatStateUsing(function ($record, $state) {
                    return $state . ' - ' . $record->toCity?->name;
                })*/,

                Tables\Columns\TextColumn::make('driver.name'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('transport_type')->sortable(),

//                Tables\Columns\TextColumn::make('transport_comfort_level')->sortable(),

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
