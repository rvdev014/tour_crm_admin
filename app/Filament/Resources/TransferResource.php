<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseStatus;
use App\Enums\TransportType;
use App\Filament\Resources\TransferResource\Pages;
use App\Filament\Resources\TransferResource\RelationManagers;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Transfer;
use App\Services\TourService;
use Filament\Forms;
use Filament\Forms\Components;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
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
                Forms\Components\Grid::make(4)->schema([

                    Forms\Components\Select::make('to_city_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label('City')
                        ->relationship('toCity', 'name')
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

                    Forms\Components\Select::make('driver_ids')
                        ->label('Drivers')
                        ->options(TourService::getDrivers())
                        ->native(false)
                        ->multiple()
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('pax')
                        ->label('Pax')
                        ->numeric(),
                ]),

                Forms\Components\Grid::make(4)->schema([

                    Forms\Components\Select::make('status')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(ExpenseStatus::class)
                        ->label('Status'),

                    Forms\Components\Select::make('transport_type')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label('Transport type')
                        ->options(TransportType::class)
                        ->reactive(),

                    Forms\Components\TextInput::make('place_of_submission')
                        ->label('Pickup Location'),

                    Forms\Components\DateTimePicker::make('date_time')
                        ->displayFormat('d.m.Y H:i')
//                        ->native(false)
                        ->seconds(false),
                ]),

                Forms\Components\Grid::make(4)->schema([

                    Forms\Components\TextInput::make('route'),
                    Forms\Components\TextInput::make('mark')
                        ->label('Marka'),
                    Forms\Components\TextInput::make('nameplate')
                        ->label('Табличка'),
                    Forms\Components\TextInput::make('sell_price')->numeric(),
                ]),

                Forms\Components\Grid::make(4)->schema([
                    /*Forms\Components\TextInput::make('price')
                        ->label('Sell price')
                        ->numeric(),*/
                    Forms\Components\TextInput::make('buy_price')->numeric(),
                    Forms\Components\Textarea::make('comment'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->modifyQueryUsing(fn($query) => $query->with(['toCity', 'company', 'createdBy']))
            ->filtersFormColumns(3)
            ->recordClasses(function ($record) {
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
                Tables\Filters\Filter::make('today')
                    ->columnSpanFull()
                    ->form([
                        Components\Grid::make(6)->schema([
                            Components\Checkbox::make('today')
                                ->label('Today')
                                ->default(false),
                            Components\Checkbox::make('tomorrow')
                                ->label('Tomorrow')
                                ->default(false),
                            Components\Select::make('companies')
                                ->native(false)
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->label('Company')
                                ->relationship('company', 'name'),
                            Components\Select::make('statuses')
                                ->native(false)
                                ->multiple()
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
                        } else {
                            if ($data['today']) {
                                $query = $query->whereDate('date_time', Carbon::today());
                            }
                            if ($data['tomorrow']) {
                                $tomorrow = Carbon::tomorrow();
                                $query = $query->whereDate('date_time', $tomorrow);
                            }
                        }
                        if ($data['statuses']) {
                            $query = $query->whereIn('status', $data['statuses']);
                        }
                        if ($data['companies']) {
                            $query = $query->whereIn('company_id', $data['companies']);
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
                        if ($data['statuses']) {
                            $query = $query->whereIn('status', $data['statuses']);
                            $statuses = collect($data['statuses'])->map(
                                fn($status) => ExpenseStatus::from($status)->getLabel()
                            )->join(', ');
                            $indicators['statuses'] = 'Status: ' . $statuses . " ({$query->count()})";
                        }
                        if ($data['companies']) {
                            $query = $query->whereIn('company_id', $data['companies']);
                            $companies = Company::query()->whereIn('id', $data['companies'])->get();
                            $companyNames = $companies->map(fn($company) => $company->name)->join(', ');
                            $indicators['company_id'] = $companyNames . " ({$query->count()})";
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
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('date_time', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Number')
                    ->formatStateUsing(function ($record) {
                        return $record->getNumber();
                    }),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company'),

                Tables\Columns\TextColumn::make('date_time')
                    ->label('Date & Time')
                    ->dateTime()
                    ->formatStateUsing(function ($state) {
                        return <<<HTML
<div style="text-align: center">
    <p>{$state->format('d.m.Y')} {$state->format('H:i')}</p>
</div>
HTML;
                    })
                    ->html()
                    ->sortable(),

                Tables\Columns\TextColumn::make('place_of_submission')
                    ->label('Pickup location'),

                Tables\Columns\TextColumn::make('route')
                    ->label('Route')
                    ->limit(50),

                Tables\Columns\TextColumn::make('pax')
                    ->formatStateUsing(function ($record, $state) {
                        return $state . ' pax';
                    })
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('toCity.name')
                    ->label('Location')
                /*->formatStateUsing(function ($record, $state) {
                    return $state . ' - ' . $record->toCity?->name;
                })*/,

                Tables\Columns\TextColumn::make('driver_ids')
                    ->label('Drivers')
                    ->formatStateUsing(function ($record) {
                        if (empty($record->driver_ids)) {
                            return '';
                        }

                        $drivers = Driver::query()->find($record->driver_ids);
                        return $drivers->map(fn($driver) => $driver->name)->join(', ');
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('createdBy.name'),

                Tables\Columns\TextColumn::make('transport_type')->sortable(),

                //                Tables\Columns\TextColumn::make('transport_comfort_level')->sortable(),

                //                Tables\Columns\TextColumn::make('group_number')->sortable(),

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
