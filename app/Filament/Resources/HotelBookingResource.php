<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseType;
use App\Filament\Resources\HotelBookingResource\Pages;
use App\Filament\Resources\HotelBookingResource\RelationManagers;
use App\Models\HotelBooking;
use App\Models\TourDayExpense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HotelBookingResource extends Resource
{
    protected static ?string $model = TourDayExpense::class;
    protected static ?string $label = 'Hotel Bookings';

    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query
                    ->with(['tour', 'hotel'])
                    ->where('type', ExpenseType::Hotel);
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->formatStateUsing(function (TourDayExpense $record) {
                        $tour = $record->tour ?? $record->tourDay->tour;
                        if ($tour->isCorporate()) {
                            $link = "/admin/tour-corporate/$tour->id/edit";
                        }  else {
                            $link = "/admin/tour-tps/$tour->id/edit";
                        }
                        return "<a href='{$link}' target='_blank'>$tour->group_number</a>";
                    })
                    ->color('info')
                    ->html(),
                Tables\Columns\TextColumn::make('hotel.name')
                    ->formatStateUsing(function (TourDayExpense $record) {
                        return "<a href='/admin/hotels/$record->hotel_id/edit' target='_blank'>{$record->hotel->name}</a>";
                    })
                    ->color('info')
                    ->html(),
                Tables\Columns\TextColumn::make('hotel.booking_cancellation_days')
                    ->label('Expiry period')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hotel.id')
                    ->label('Expires at')
                    ->formatStateUsing(function (TourDayExpense $record) {
                        $bookingDate = $record->tourDay?->date ?? $record->date;
                        $diff = $bookingDate->diffInDays(now());
                        return $bookingDate->gt(now()) ? $diff : 0;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('hotel.inn')
                    ->label('Pax')
                    ->formatStateUsing(function (TourDayExpense $record) {
                        $tour = $record->tour ?? $record->tourDay->tour;
                        return $tour->getTotalPax();
                    })
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
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
            'index' => Pages\ListHotelBookings::route('/'),
            'create' => Pages\CreateHotelBooking::route('/create'),
            'edit' => Pages\EditHotelBooking::route('/{record}/edit'),
        ];
    }
}
