<?php

namespace App\Support;

use App\Enums\DriverTransferStatus;
use App\Enums\ExpenseStatus;
use App\Models\Driver;
use App\Models\Transfer;
use App\Services\ExportTransferService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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

    /**
     * Who is driving this trip. ALWAYS empty for a plain driver — only a dispatcher's view passes the
     * drivers in — so one driver can never see a co-driver's phone number.
     *
     * @var list<array{name: string, phone: ?string, tel: ?string, car: ?string}>
     */
    public readonly array $assignedDrivers;

    /** Dispatcher view only: nobody is assigned yet (worth flagging). False when drivers were not passed in. */
    public readonly bool $hasNoDriver;

    /**
     * The client's contact links (call / WhatsApp / Telegram), or null when there is no usable number or
     * the viewer is not meant to have it. Off unless the caller asks for it: see DriverTransferController.
     *
     * @var array{display: string, tel: string, whatsapp: ?string, telegram: ?string}|null
     */
    public readonly ?array $clientContact;

    /**
     * @param  Collection<int, Driver>|null  $drivers  from DriverTransferQuery::driversFor(); pass it for
     *                                                 dispatchers only
     * @param  bool  $showClientPhone  false by default, so a call site that forgets the argument leaks nothing.
     *                                 The controller passes true for dispatchers, and for drivers only while
     *                                 the trip is still open.
     */
    public function __construct(Transfer $transfer, ?Collection $drivers = null, bool $showClientPhone = false)
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
        $this->clientContact = $showClientPhone ? ClientContact::from($transfer->client_phone) : null;

        $this->status = $transfer->effectiveDriverStatus();
        $this->statusChangedAt = $transfer->driver_status_updated_at;
        $this->isClosed = $transfer->status === ExpenseStatus::Done;

        $assigned = [];
        if ($drivers !== null) {
            foreach ((array) $transfer->driver_ids as $id) {
                if ($driver = $drivers->get((int) $id)) {
                    $assigned[] = self::person($driver->name, $driver->phone, self::car($driver));
                }
            }

            // No Driver record, but the operator typed who is actually driving (free text on the transfer).
            if ($assigned === []) {
                $name = self::clean($transfer->driver_name);
                $phone = self::clean($transfer->driver_phone);

                if ($name !== null || $phone !== null) {
                    $assigned[] = self::person($name ?? $phone, $phone, null);
                }
            }
        }
        $this->assignedDrivers = $assigned;
        $this->hasNoDriver = $drivers !== null && $assigned === [];

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

    /**
     * @return array{name: string, phone: ?string, tel: ?string, car: ?string}
     */
    private static function person(string $name, ?string $phone, ?string $car): array
    {
        $phone = self::clean($phone);

        return ['name' => $name, 'phone' => $phone, 'tel' => PhoneNormalizer::tel($phone), 'car' => $car];
    }

    private static function car(Driver $driver): ?string
    {
        return implode(' · ', array_filter([self::clean($driver->car_model), self::clean($driver->car_number)])) ?: null;
    }

    /** Operators type "-" for "nothing" in several free-text fields; treat it as empty. */
    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return ($value === '' || $value === '-') ? null : $value;
    }
}
