<?php

namespace App\Filament\Resources\TourTpsResource\Actions;

use App\Models\Tour;
use Closure;
use Filament\Actions\StaticAction;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StatusAction extends Action
{
    protected ?Closure $mutateRecordDataUsing = null;

    public static function getDefaultName(): ?string
    {
        return 'status_view';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Statuses');

        $this->modalHeading(fn (Tour $record) => "TPS {$record->group_number} | {$record->country->name} | Pax: {$record->getTotalPax()} | Arrival: {$record->start_date->format('d.m.Y')} | Departure: {$record->end_date->format('d.m.Y')}");

        $this->modalSubmitAction(fn (StaticAction $action, $record) => $action->url(route('filament.admin.resources.tour-tps.edit', $record->id))->label('Edit'));
        $this->modalCancelAction(fn (StaticAction $action) => $action->label(__('filament-actions::view.single.modal.actions.close.label')));

        $this->modalWidth(MaxWidth::ScreenExtraLarge);

        $this->modalContent(function (Model $record, Table $table) {
            return view('actions.status_view', [
                'record' => $record,
                'table' => $table,
            ]);
        });

        $this->color('gray');

        $this->icon(FilamentIcon::resolve('actions::view-action') ?? 'heroicon-m-eye');

        $this->disabledForm();

        $this->fillForm(function (Model $record, Table $table): array {
            if ($translatableContentDriver = $table->makeTranslatableContentDriver()) {
                $data = $translatableContentDriver->getRecordAttributesToArray($record);
            } else {
                $data = $record->attributesToArray();
            }

            if ($this->mutateRecordDataUsing) {
                $data = $this->evaluate($this->mutateRecordDataUsing, ['data' => $data]);
            }

            return $data;
        });

        $this->action(static function (): void {});
    }

    public function mutateRecordDataUsing(?Closure $callback): static
    {
        $this->mutateRecordDataUsing = $callback;

        return $this;
    }
}
