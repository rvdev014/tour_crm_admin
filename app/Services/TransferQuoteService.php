<?php

namespace App\Services;

use App\Enums\TransferBookingStatus;
use App\Enums\TransferLegDirection;
use App\Enums\TransferRequestStatus;
use App\Mail\TransferBookingSubmittedMail;
use App\Models\TransferBooking;
use App\Models\TransferExtra;
use App\Models\TransferRequest;
use App\Models\TransportClass;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * The stateless quote -> booking pipeline for the public transfer flow.
 *
 * A "quote" is never written to the database. It's a signed, expiring
 * token carrying everything needed to reprice the trip later, so a
 * customer can browse vehicle options anonymously and the price the
 * server ultimately charges at booking time is always recomputed from
 * live data — never trusted from the client (see decodeToken() /
 * createBooking()).
 */
class TransferQuoteService
{
    private const TOKEN_TTL_MINUTES = 30;

    /**
     * Build a priced quote for a trip. $params keys: from, to, from_coords,
     * to_coords, date, time, passengers, trip_type ('one_way'|'return'),
     * return_date, return_time (both required when trip_type = 'return').
     *
     * @return array{quote_token: string, expires_at: string, distance_km: float, legs: array, options: array}
     */
    public function quote(array $params, string $timezone): array
    {
        $distance = GeoDistance::distanceBetweenCoordStrings($params['from_coords'], $params['to_coords']);

        if ($distance === null || $distance <= 0) {
            throw new RuntimeException('Unable to determine distance between the selected locations.');
        }

        $isRoundTrip = ($params['trip_type'] ?? 'one_way') === 'return';
        $issuedAt = now();

        $legs = [
            [
                'direction' => TransferLegDirection::Departure->value,
                'from' => $params['from'],
                'to' => $params['to'],
                'from_coords' => $params['from_coords'],
                'to_coords' => $params['to_coords'],
                'date_time' => $this->combineDateTime($params['date'], $params['time'], $timezone),
            ],
        ];

        if ($isRoundTrip) {
            $legs[] = [
                'direction' => TransferLegDirection::Return->value,
                'from' => $params['to'],
                'to' => $params['from'],
                'from_coords' => $params['to_coords'],
                'to_coords' => $params['from_coords'],
                'date_time' => $this->combineDateTime($params['return_date'], $params['return_time'], $timezone),
            ];
        }

        $passengers = (int) $params['passengers'];
        $options = $this->priceOptions($distance, $passengers, count($legs));

        $payload = [
            'from' => $params['from'],
            'to' => $params['to'],
            'from_coords' => $params['from_coords'],
            'to_coords' => $params['to_coords'],
            'distance' => $distance,
            'passengers' => $passengers,
            'trip_type' => $isRoundTrip ? 'return' : 'one_way',
            'date' => $params['date'],
            'time' => $params['time'],
            'return_date' => $params['return_date'] ?? null,
            'return_time' => $params['return_time'] ?? null,
            'timezone' => $timezone,
            'issued_at' => $issuedAt->timestamp,
        ];

        return [
            'quote_token' => Crypt::encryptString(json_encode($payload)),
            'expires_at' => $issuedAt->copy()->addMinutes(self::TOKEN_TTL_MINUTES)->toIso8601String(),
            'distance_km' => round($distance, 1),
            'legs' => $legs,
            'options' => $options,
        ];
    }

    /**
     * Price every eligible TransportClass for a given distance/passenger
     * count. If any class fits the whole group in one vehicle, only those
     * are returned (vehicle_count = 1) — this is what keeps a 6-person
     * party from ever being offered a 4-seat Sedan. Only when nothing
     * fits do we fall back to every class with a multiplied "x N vehicles"
     * price.
     */
    public function priceOptions(float $distance, int $passengers, int $legCount = 1): array
    {
        $classes = TransportClass::query()
            ->whereNotNull('passenger_capacity')
            ->where('passenger_capacity', '>', 0)
            ->whereNotNull('price_per_km')
            ->orderBy('order')
            ->get();

        $fitting = $classes->filter(fn (TransportClass $class) => $class->passenger_capacity >= $passengers);
        $candidates = $fitting->isNotEmpty() ? $fitting : $classes;

        return $candidates
            ->map(function (TransportClass $class) use ($distance, $passengers, $legCount) {
                $vehicleCount = (int) max(1, ceil($passengers / $class->passenger_capacity));
                $pricePerVehicle = TransferService::calculateFare($distance, $class);
                $total = round($pricePerVehicle * $vehicleCount * $legCount, 2);

                return [
                    'transport_class' => $class,
                    'max_people' => $class->passenger_capacity * $vehicleCount,
                    'vehicle_count' => $vehicleCount,
                    'price_per_vehicle' => $pricePerVehicle,
                    'total' => $total,
                    'currency' => 'USD',
                ];
            })
            ->sortBy('total')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeToken(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new RuntimeException('This quote is invalid. Please search again.');
        }

        $issuedAt = Carbon::createFromTimestamp($payload['issued_at'] ?? 0);
        if ($issuedAt->addMinutes(self::TOKEN_TTL_MINUTES)->isPast()) {
            throw new RuntimeException('This quote has expired. Please search again.');
        }

        return $payload;
    }

    /**
     * Recompute the quote from the token payload against live data and
     * create the booking + its 1-2 legs + extras in one transaction.
     * Nothing about price is ever taken from $input — only quote_token,
     * transport_class_id, extras and the billing fields are.
     *
     * @param  array<string, mixed>  $input
     */
    public function createBooking(array $input, ?int $userId): TransferBooking
    {
        $quote = $this->decodeToken($input['quote_token']);
        $isRoundTrip = $quote['trip_type'] === 'return';
        $legCount = $isRoundTrip ? 2 : 1;

        $options = $this->priceOptions((float) $quote['distance'], (int) $quote['passengers'], $legCount);
        $selected = collect($options)->firstWhere('transport_class.id', (int) $input['transport_class_id']);

        if (! $selected) {
            throw new RuntimeException('The selected vehicle is no longer available for this trip. Please search again.');
        }

        /** @var TransportClass $transportClass */
        $transportClass = $selected['transport_class'];
        $pricePerVehicle = $selected['price_per_vehicle'];
        $vehicleCount = $selected['vehicle_count'];
        $perLegTotal = round($pricePerVehicle * $vehicleCount, 2);
        $transfersTotal = round($perLegTotal * $legCount, 2);

        $extrasInput = collect($input['extras'] ?? []);
        $extras = $extrasInput->isEmpty()
            ? collect()
            : TransferExtra::query()
                ->whereIn('id', $extrasInput->pluck('id'))
                ->where('is_active', true)
                ->get()
                ->map(function (TransferExtra $extra) use ($extrasInput) {
                    $quantity = (int) $extrasInput->firstWhere('id', $extra->id)['quantity'];
                    $quantity = max(1, min($quantity, $extra->max_quantity));

                    return [
                        'extra' => $extra,
                        'quantity' => $quantity,
                        'unit_price' => (float) $extra->price,
                        'total_price' => round((float) $extra->price * $quantity, 2),
                    ];
                });

        $extrasTotal = round($extras->sum('total_price'), 2);
        $total = round($transfersTotal + $extrasTotal, 2);

        $booking = DB::transaction(function () use ($input, $userId, $quote, $isRoundTrip, $transportClass, $vehicleCount, $perLegTotal, $transfersTotal, $extrasTotal, $total, $extras) {
            $booking = TransferBooking::query()->create([
                'user_id' => $userId,
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'hotel_address' => $input['hotel_address'],
                'flight_data' => $input['flight_data'] ?? null,
                'notes' => $input['notes'] ?? null,
                'is_round_trip' => $isRoundTrip,
                'transfers_total' => $transfersTotal,
                'extras_total' => $extrasTotal,
                'total' => $total,
                'currency' => 'USD',
                'status' => TransferBookingStatus::New,
                'submitted_at' => now(),
            ]);

            foreach ($extras as $row) {
                $booking->extras()->attach($row['extra']->id, [
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'total_price' => $row['total_price'],
                ]);
            }

            $fullName = trim($input['first_name'].' '.$input['last_name']);
            $legDefinitions = [
                [
                    'direction' => TransferLegDirection::Departure,
                    'from' => $quote['from'],
                    'to' => $quote['to'],
                    'from_coords' => $quote['from_coords'],
                    'to_coords' => $quote['to_coords'],
                    'date' => $quote['date'],
                    'time' => $quote['time'],
                ],
            ];

            if ($isRoundTrip) {
                $legDefinitions[] = [
                    'direction' => TransferLegDirection::Return,
                    'from' => $quote['to'],
                    'to' => $quote['from'],
                    'from_coords' => $quote['to_coords'],
                    'to_coords' => $quote['from_coords'],
                    'date' => $quote['return_date'],
                    'time' => $quote['return_time'],
                ];
            }

            foreach ($legDefinitions as $leg) {
                TransferRequest::query()->create([
                    'transfer_booking_id' => $booking->id,
                    'direction' => $leg['direction'],
                    'status' => TransferRequestStatus::Booked,
                    'user_id' => $userId,
                    'from' => $leg['from'],
                    'to' => $leg['to'],
                    'from_coords' => $leg['from_coords'],
                    'to_coords' => $leg['to_coords'],
                    'distance' => $quote['distance'],
                    'date_time' => $this->combineDateTime($leg['date'], $leg['time'], $quote['timezone']),
                    'passengers_count' => $quote['passengers'],
                    'transport_class_id' => $transportClass->id,
                    'vehicle_count' => $vehicleCount,
                    'total_fare' => $perLegTotal,
                    'fio' => $fullName,
                    'phone' => $input['phone'],
                    'terminal_name' => $input['hotel_address'],
                    'comment' => $input['notes'] ?? null,
                ]);
            }

            return $booking->fresh(['legs.transportClass', 'extras']);
        });

        // Sent after the transaction commits — a mail-server hiccup must
        // never roll back an otherwise-successful booking.
        try {
            Mail::to($booking->email)->send(new TransferBookingSubmittedMail($booking));
        } catch (\Throwable $e) {
            Log::error('Failed to send transfer booking submitted mail: '.$e->getMessage());
        }

        return $booking;
    }

    /**
     * Promote every leg of a booking into operational Transfer records —
     * one per physical vehicle, so a leg with vehicle_count = 2 becomes
     * two Transfer rows for the driver-assignment/export pipeline.
     *
     * @return Collection<int, \App\Models\Transfer>
     */
    public function acceptBooking(TransferBooking $booking): Collection
    {
        return DB::transaction(function () use ($booking) {
            $transfers = collect();

            /** @var TransferRequest $leg */
            foreach ($booking->legs as $leg) {
                for ($i = 0; $i < max(1, $leg->vehicle_count); $i++) {
                    $transfers->push(\App\Models\Transfer::query()->create([
                        'from' => $leg->from,
                        'to' => $leg->to,
                        'from_coords' => $leg->from_coords,
                        'to_coords' => $leg->to_coords,
                        'date_time' => $leg->date_time,
                        'pax' => $leg->passengers_count,
                        'route' => $leg->from.' - '.$leg->to,
                        'passenger' => $leg->fio,
                        'comment' => $leg->comment,
                        'nameplate' => $leg->text_on_sign,
                        'requested_by' => $leg->fio,
                        'status' => \App\Enums\ExpenseStatus::New,
                        'location_details' => $leg->terminal_name,
                        'transport_class_id' => $leg->transport_class_id,
                        'sell_price' => $leg->total_fare,
                        'price' => $leg->total_fare,
                        'sell_price_currency' => $booking->currency,
                        'transfer_request_id' => $leg->id,
                    ]));
                }
            }

            $booking->update(['status' => TransferBookingStatus::Confirmed]);

            return $transfers;
        });
    }

    private function combineDateTime(string $date, string $time, string $timezone): Carbon
    {
        try {
            return Carbon::parse("{$date} {$time}", $timezone)->utc();
        } catch (\Throwable) {
            return Carbon::parse("{$date} {$time}", config('app.timezone'))->utc();
        }
    }
}
