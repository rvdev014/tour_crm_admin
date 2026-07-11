<?php

namespace App\Filament\Resources\HotelResource\Actions;

use Filament\Actions\StaticAction;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class HotelPeriodsAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'hotel_periods_view';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Room prices');

        $this->modalHeading(fn (Model $record) => $record->name);

        $this->modalSubmitAction(fn (StaticAction $action, Model $record) => $action
            ->label('Edit')
            ->url(route('filament.admin.resources.hotels.edit', $record)));
        $this->modalCancelAction(fn (StaticAction $action) => $action->label('Close'));

        $this->modalWidth(MaxWidth::ExtraLarge);

        $this->modalContent(fn (Model $record) => view('actions.hotel_periods_view', [
            'record' => $record,
        ]));

        $this->color('gray');

        $this->icon(FilamentIcon::resolve('actions::view-action') ?? 'heroicon-m-eye');

        $this->action(static function (): void {});
    }
}
