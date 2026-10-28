<?php

namespace App\Livewire;

use App\Services\NewRequestsCounter;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Red pill in the admin topbar: how many website requests are waiting, refreshed every few
 * seconds, with a toast + beep the moment a new one arrives.
 *
 * "New" is judged against the newest request id each kind had the last time THIS browser session
 * looked (kept in the session, so a full page load doesn't forget it). The very first look after
 * login only sets that baseline — an operator logging in to 30 old requests isn't toasted 30 times;
 * the pill and the red rows already say so.
 */
class NewRequestsAlert extends Component
{
    private const SESSION_KEY = 'new_requests_seen';

    /** @var list<array<string, mixed>> */
    public array $summary = [];

    public int $total = 0;

    public function mount(): void
    {
        $this->check();
    }

    public function check(): void
    {
        $this->summary = NewRequestsCounter::summary();
        $this->total = NewRequestsCounter::total($this->summary);

        $seen = session(self::SESSION_KEY);
        $firstLook = $seen === null;
        $seen ??= [];

        $arrived = [];
        foreach ($this->summary as $row) {
            $previous = (int) ($seen[$row['key']] ?? 0);

            if (! $firstLook && $row['latest'] > $previous) {
                $arrived[] = $row;
            }

            // Never move the baseline backwards: handling the newest request lowers "latest".
            $seen[$row['key']] = max($previous, $row['latest']);
        }

        session([self::SESSION_KEY => $seen]);

        if ($arrived !== []) {
            $this->announce($arrived);
        }
    }

    /** @param list<array<string, mixed>> $arrived */
    private function announce(array $arrived): void
    {
        foreach ($arrived as $row) {
            Notification::make()
                ->title(__('New request').': '.$row['singular'])
                ->body(__('Waiting for an operator'))
                ->icon('heroicon-o-bell-alert')
                ->danger()
                ->persistent()
                ->actions([
                    Action::make('open')
                        ->label(__('Open'))
                        ->button()
                        ->url($row['url']),
                ])
                ->send();
        }

        $this->dispatch('new-requests-arrived');
    }

    public function render(): View
    {
        return view('livewire.new-requests-alert');
    }
}
