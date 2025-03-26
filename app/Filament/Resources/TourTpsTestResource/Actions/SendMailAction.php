<?php

namespace App\Filament\Resources\TourTpsTestResource\Actions;

use App\Models\Tour;
use App\Services\MailService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Illuminate\Database\Eloquent\Model;

class SendMailAction extends Action
{
    use CanCustomizeProcess;

    protected string $type = 'restaurants';
    protected ?Tour $tour = null;

    public static function getDefaultName(): ?string
    {
        return 'send_mail';
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function tour(Tour $tour): self
    {
        $this->tour = $tour;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label($this->label ?? 'Send mails');
        $this->modalHeading(fn(): string => $this->label ?? 'Send mails');

        $this->modalSubmitActionLabel(__('filament-actions::delete.single.modal.actions.delete.label'));

        $this->successNotificationTitle(__('filament-actions::delete.single.notifications.deleted.title'));

        $this->color('success');
        $this->groupedIcon('heroicon-o-envelope');
        $this->modalIcon('heroicon-o-envelope');
        $this->requiresConfirmation();

        $this->action(function (): void {
            if (!$this->tour) {
                $this->failureNotificationTitle('Tour is not set')->failure();
                return;
            }

            try {
                if ($this->type === 'restaurants') {
                    $this->process(fn(Model $record) => MailService::sendMailRestaurants($this->tour));
                } elseif ($this->type === 'hotels') {
                    $this->process(fn(Model $record) => MailService::sendMailHotels($this->tour));
                } else {
                    $this->failureNotificationTitle('Unknown type')->failure();
                    return;
                }

                $this->successNotificationTitle('Mails sent')->success();
            } catch (\Throwable $e) {
                $this->failureNotificationTitle(
                    app()->environment('local') ? $e->getMessage() : 'Error while sending mails'
                )->failure();
                return;
            }
        });
    }
}
