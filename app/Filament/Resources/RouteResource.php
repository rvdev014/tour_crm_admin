<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RouteResource\Pages;
use App\Models\Route;
use App\Models\TransportClass;
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
                ->description('Add cities in order from start to destination. Minimum 2 cities required.')
                ->schema([
                    Forms\Components\Repeater::make('waypoints')
                        ->relationship('waypoints')
                        ->orderColumn('order')
                        ->reorderable()
                        ->addActionLabel('Add waypoint')
                        ->deletable(true)
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

            Forms\Components\Section::make('Prices by transport class')
                ->description('Set the flat price in USD for each transport class.')
                ->schema([
                    Forms\Components\Repeater::make('prices')
                        ->relationship('prices')
                        ->addActionLabel('Add price')
                        ->deletable(true)
                        ->schema([
                            Forms\Components\Select::make('transport_class_id')
                                ->label('Transport class')
                                ->options(fn() => TransportClass::query()->orderBy('order')->pluck('name', 'id')->toArray())
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
        return parent::getEloquentQuery()->with(['waypoints.city', 'prices.transportClass']);
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
                            $name = $p->transportClass?->name ?? "#{$p->transport_class_id}";
                            return $name . ': $' . number_format($p->price, 0);
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
}
