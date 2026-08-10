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
    protected static ?string $model = TourDayExpense::class;
    protected static ?string $label = 'Hotel Bookings';

    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->modifyQueryUsing(function (Builder $query) {
                $query
                    ->with(['tour', 'hotel'])
                    ->where('type', ExpenseType::Hotel);
            })
            ->columns([
                Tables\Columns\TextColumn::make('tour_id')
                    ->getStateUsing(function (TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay?->tour;
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
                    ->label(__('Expiry period'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('Expires at'))
                    ->getStateUsing(function (TourDayExpense $record) {
                        $bookingDate = $record->tourDay?->date ?? $record->date;
                        $diff = $bookingDate->diffInDays(now());
                        return 'in ' . ($bookingDate->gt(now()) ? $diff : 0) . ' days';
                    }),

                Tables\Columns\TextColumn::make('tour_pax')
                    ->label(__('Pax'))
                    ->getStateUsing(function (TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                        return $tour->getTotalPax();
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
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
