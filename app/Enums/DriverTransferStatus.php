<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Driver-side progress of a transfer, set by the driver from the cabinet.
 *
 * Deliberately separate from ExpenseStatus (Transfer::$status): that one is the operations lifecycle
 * (New/Confirmed/Rejected/Done), mirrored both ways into tour_day_expenses. This one lives on
 * transfers only and is never mirrored.
 *
 * A NULL column means "the driver has not touched it yet" and is read as Assigned
 * (see Transfer::effectiveDriverStatus()).
 */
enum DriverTransferStatus: string implements HasColor, HasIcon, HasLabel
{
    // Declaration order IS the lifecycle order — order() and next() rely on it.
    case Assigned = 'assigned';
    case EnRouteToClient = 'en_route_to_client';
    case WaitingForClient = 'waiting_for_client';
    case OnTheWay = 'on_the_way';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return __('driver.statuses.'.$this->value);
    }

    /** Text of the button that moves a transfer into this status. */
    public function getActionLabel(): string
    {
        return __('driver.actions.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Assigned => 'gray',
            self::EnRouteToClient => 'info',
            self::WaitingForClient => 'warning',
            self::OnTheWay => 'primary',
            self::Completed => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Assigned => 'heroicon-o-clipboard-document-check',
            self::EnRouteToClient => 'heroicon-o-arrow-right-circle',
            self::WaitingForClient => 'heroicon-o-clock',
            self::OnTheWay => 'heroicon-o-truck',
            self::Completed => 'heroicon-o-check-circle',
        };
    }

    public function order(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function next(): ?self
    {
        return self::cases()[$this->order() + 1] ?? null;
    }

    /** A driver may only step forward by exactly one status. */
    public function canAdvanceTo(self $to): bool
    {
        return $to === $this->next();
    }

    public function isFinal(): bool
    {
        return $this->next() === null;
    }
}
