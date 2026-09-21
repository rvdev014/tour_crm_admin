<?php

namespace App\Support;

use App\Enums\DriverTransferStatus;
use App\Enums\ExpenseStatus;
use App\Models\Transfer;
use App\Services\ExportTransferService;
use Carbon\Carbon;

/**
 * What the driver cabinet is allowed to know about a transfer.
 *
 * Views receive this, never the Eloquent model, so a template physically cannot reach
 * $transfer->sell_price and friends. Together with DriverTransferQuery::COLUMNS (which never fetches
 * them) that makes "drivers never see prices" two independent barriers, not one promise.
 */
final class DriverTransferPresenter
{
    public readonly int $id;

    public readonly int $number;

    /** transfers.date_time is nullable; a dateless row must not fatal when opened by direct link. */
    public readonly ?Carbon $dateTime;

    public readonly ?string $route;

    public readonly ?string $pickup;

    public readonly ?string $terminal;

    public readonly ?string $city;

    public readonly ?int $pax;

    public readonly ?string $nameplate;

    public readonly ?string $mark;

    public readonly string $transportType;

    public readonly ?string $comment;

    public readonly ?string $passenger;

    public readonly DriverTransferStatus $status;

    public readonly ?Carbon $statusChangedAt;

    /** Closed by the operator: the driver can look, not act. */
    public readonly bool $isClosed;

    public readonly ?string $destinationMapUrl;

    public readonly ?string $pickupMapUrl;

    public function __construct(Transfer $transfer)
    {
        $this->id = $transfer->id;
        // Same formula as the voucher export and the Telegram message, so a driver, a client and an
        // operator all quote the same number.
        $this->number = 1000 + $transfer->id;
        $this->dateTime = $transfer->date_time;

        $to = self::clean($transfer->to);
        $from = self::clean($transfer->from);
        $this->route = self::clean($transfer->route) ?? ($from && $to ? "{$from} → {$to}" : ($to ?? $from));
        $this->pickup = self::clean($transfer->place_of_submission) ?? $from;
        $this->terminal = self::clean($transfer->location_details);

        $toCity = self::clean($transfer->toCity?->name);
        $fromCity = self::clean($transfer->fromCity?->name);
        $this->city = $toCity;

        $this->pax = $transfer->pax;
        $this->nameplate = self::clean($transfer->nameplate);
        $this->mark = self::clean($transfer->mark);
        $this->transportType = ExportTransferService::getTransportTypeLabel($transfer->transport_type);
        $this->comment = self::clean($transfer->comment);
        $this->passenger = self::clean($transfer->passenger);

        $this->status = $transfer->effectiveDriverStatus();
        $this->statusChangedAt = $transfer->driver_status_updated_at;
        $this->isClosed = $transfer->status === ExpenseStatus::Done;

        // Customer-entered `to` is a real place name; `route` may be a "A - B" trip description
        // (API-created transfers), which a map search would resolve badly. Prefer `to`.
        //
        // Coordinates (copied from the API request) describe the customer's own from/to, so they are
        // used only alongside those: the destination when we mapped from `to`, and the pickup when
        // it is the customer's `from` rather than a place_of_submission an operator typed over it.
        $this->destinationMapUrl = MapLink::yandex($to ?? $this->route, $to !== null ? $transfer->to_coords : null, $toCity);
        $this->pickupMapUrl = MapLink::yandex(
            $this->pickup,
            self::clean($transfer->place_of_submission) === null ? $transfer->from_coords : null,
            $fromCity ?? $toCity,
        );
    }

    public function next(): ?DriverTransferStatus
    {
        return $this->isClosed ? null : $this->status->next();
    }

    /** Operators type "-" for "nothing" in several free-text fields; treat it as empty. */
    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return ($value === '' || $value === '-') ? null : $value;
    }
}
