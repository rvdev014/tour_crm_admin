<?php

namespace App\Filament\Resources\TransferResource\Pages;

use App\Enums\ExpenseStatus;
use App\Filament\Resources\TransferResource;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Throwable;

use function Filament\Support\is_app_url;

class CreateTransfer extends CreateRecord
{
    protected static string $resource = TransferResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill();

        $this->callHook('afterFill');
    }

    public function create(bool $another = false): void
    {
        $this->authorizeAccess();

        try {
            $this->beginDatabaseTransaction();

            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeCreate($data);

            $this->callHook('beforeCreate');

            $this->record = $this->handleRecordCreation($data);

            $this->form->model($this->getRecord())->saveRelationships();

            $this->callHook('afterCreate');

            $this->commitDatabaseTransaction();
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->rememberData();

        $this->getCreatedNotification()?->send();

        if ($another) {
            $oldState = $this->form->getState();
            // Ensure that the form record is anonymized so that relationships aren't loaded.
            $this->form->model($this->getRecord()::class);
            $this->record = null;

            $this->form->fill($oldState);

            $this->callHook('afterFill');

            return;
        }

        $redirectUrl = $this->getRedirectUrl();

        $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode() && is_app_url($redirectUrl));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        ExpenseService::convertExpensePrice($data, 'sell_price');
        ExpenseService::convertExpensePrice($data, 'buy_price');

        $data['sell_price_result'] = $data['sell_price_converted'] ?? $data['sell_price'] ?? 0;
        $data['buy_price_result'] = $data['buy_price_converted'] ?? $data['buy_price'] ?? 0;

        return $data;
    }

    protected function afterCreate(): void
    {
        $data = $this->record;
        if ($data['status'] == ExpenseStatus::Confirmed) {
            TourService::sendTelegramTransfer($data);
        }
    }
}
