<?php

namespace App\Filament\Resources;

use Filament\Tables;
use App\Models\Hotel;
use App\Enums\RateEnum;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Tables\Columns\PeriodsColumn;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\HotelSalesResource\Pages;

class HotelSalesResource extends Resource
{
    protected static ?string $model = Hotel::class;
    protected static ?string $label = 'Hotel Sales';
    
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationGroup = 'Manual';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'inn', 'company_name', 'address', 'phones.phone_number'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
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
                Tables\Columns\TextColumn::make('rate')
                    ->label('Rate')
                    ->getStateUsing(fn($record) => RateEnum::tryFrom($record->rate)?->getLabel())
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordUrl(null)
            //            ->recordAction(HotelPeriodsAction::class)
            ->actions([
//                Tables\Actions\EditAction::make(),
                //                HotelPeriodsAction::make()->label('')->icon(''),
            ], position: Tables\Enums\ActionsPosition::BeforeColumns)
            /*->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])*/;
    }
    
    public static function canEdit(Model $record): bool
    {
        return false;
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHotels::route('/'),
        ];
    }
}
