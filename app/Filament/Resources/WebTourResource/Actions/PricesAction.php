<?php

namespace App\Filament\Resources\TourResource\Actions;

use Closure;
use Filament\Actions\StaticAction;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PricesAction extends Action
{
    protected ?Closure $mutateRecordDataUsing = null;

    public static function getDefaultName(): ?string
    {
        return 'prices_view';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Statuses');

        $this->modalHeading(fn(Model $record) => "Prices for tour: {$record->name}");

        $this->modalSubmitAction(
            fn(StaticAction $action, $record) => $action->url(
                route('filament.admin.resources.tours.edit', $record->id)
            )->label(__('filament-actions::edit.single.label'))
        );
        $this->modalCancelAction(
            fn(StaticAction $action) => $action->label(__('filament-actions::view.single.modal.actions.close.label'))
        );

        $this->modalWidth(MaxWidth::FiveExtraLarge);

        $this->modalContent(function (Model $record, Table $table) {
            return view('actions.prices_view', [
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

        $this->action(static function (): void {
        });
    }

    public function mutateRecordDataUsing(?Closure $callback): static
    {
        $this->mutateRecordDataUsing = $callback;

        return $this;
    }
}
