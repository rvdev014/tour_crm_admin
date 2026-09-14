<?php

namespace App\Http\Resources;

use App\Models\TransferBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransferBooking
 */
class TransferBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status,
            'is_round_trip' => $this->is_round_trip,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'hotel_address' => $this->hotel_address,
            'flight_data' => $this->flight_data,
            'notes' => $this->notes,
            'transfers_total' => (float) $this->transfers_total,
            'extras_total' => (float) $this->extras_total,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'legs' => $this->whenLoaded('legs', fn () => $this->legs->map(fn ($leg) => [
                'direction' => $leg->direction,
                'from' => $leg->from,
                'to' => $leg->to,
                'date_time' => $leg->date_time,
                'passengers_count' => $leg->passengers_count,
                'vehicle_count' => $leg->vehicle_count,
                'transport_class' => new TransportClassResource($leg->transportClass),
                'total_fare' => (float) $leg->total_fare,
            ])),
            'extras' => $this->whenLoaded('extras', fn () => $this->extras->map(fn ($extra) => [
                'id' => $extra->id,
                'name' => $extra->name,
                'quantity' => $extra->pivot->quantity,
                'unit_price' => (float) $extra->pivot->unit_price,
                'total_price' => (float) $extra->pivot->total_price,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
