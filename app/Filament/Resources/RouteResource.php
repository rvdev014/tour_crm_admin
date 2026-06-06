<?php

namespace App\Filament\Resources;

use App\Enums\TransportType;
use App\Filament\Resources\RouteResource\Pages;
use App\Models\Route;
use App\Models\Transport;
use App\Services\TourService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RouteResource extends Resource
{
    protected static ?string $model = Route::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationGroup = 'Manual';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Waypoints')
                ->description('Add cities in order from start to destination.')
                ->schema([
                    Forms\Components\Repeater::make('waypoints')
                        ->relationship('waypoints')
                        ->orderColumn('order')
                        ->reorderable()
                        ->addActionLabel('Add waypoint')
                        ->minItems(2)
                        ->schema([
                            Forms\Components\Select::make('city_id')
                                ->label('City')
                                ->options(fn() => TourService::getCities())
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->columns(1),
                ]),

            Forms\Components\Section::make('Prices by transport type')
                ->description('Set the price in USD for each transport type.')
                ->schema([
                    Forms\Components\Repeater::make('prices')
                        ->relationship('prices')
                        ->addActionLabel('Add price')
                        ->schema([
                            Forms\Components\Select::make('transport_type')
                                ->label('Transport type')
                                ->options(fn() => self::getAvailableTransportTypes())
                                ->native(false)
                                ->searchable()
                                ->required(),
                            Forms\Components\TextInput::make('price')
                                ->label('Price (USD)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ])
                        ->columns(2),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['waypoints.city', 'prices']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Route')
                    ->searchable(query: function($query, $search) {
                        $query->whereHas('waypoints.city', fn($q) => $q->where('name', 'like', "%$search%"));
                    }),
                Tables\Columns\TextColumn::make('prices_summary')
                    ->label('Prices (USD)')
                    ->getStateUsing(function(Route $record) {
                        return $record->prices->map(function($p) {
                            $enum = $p->transport_type instanceof TransportType
                                ? $p->transport_type
                                : TransportType::from((int)$p->transport_type);
                            return $enum->getLabel() . ': $' . number_format($p->price, 0);
                        })->join(', ');
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoutes::route('/'),
            'create' => Pages\CreateRoute::route('/create'),
            'edit'   => Pages\EditRoute::route('/{record}/edit'),
        ];
    }

    public static function getAvailableTransportTypes(): array
    {
        // pluck with cast returns TransportType enums; extract value for option keys
        $types = Transport::query()
            ->distinct()
            ->pluck('type')
            ->map(fn($t) => $t instanceof TransportType ? $t->value : (int)$t)
            ->unique()
            ->toArray();

        $options = [];
        foreach ($types as $typeValue) {
            $enum = TransportType::from($typeValue);
            $options[$typeValue] = $enum->getLabel();
        }
        return $options;
    }
}
