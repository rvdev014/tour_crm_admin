<?php

namespace App\Http\Resources;

use App\Models\TransportClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportClass
 */
class TransportClassResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_per_km' => $this->price_per_km,
            'photo' => $this->photo ? asset('storage/' . $this->photo) : null,
            'passenger_capacity' => $this->passenger_capacity,
            'luggage_capacity' => $this->luggage_capacity,
            'waiting_time_included' => $this->waiting_time_included,
            'meeting_with_place' => $this->meeting_with_place,
            'non_refundable_rate' => $this->non_refundable_rate,
            'vehicle_example' => $this->vehicle_example,
        ];
    }
}