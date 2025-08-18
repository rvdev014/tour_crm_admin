<?php

namespace App\Http\Resources;

use App\Models\TransferRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransferRequest
 */
class TransferRequestResource extends JsonResource
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
            'from_city_id' => $this->from_city_id,
            'to_city_id' => $this->to_city_id,
            'date_time' => $this->date_time,
            'passengers_count' => $this->passengers_count,
            'transport_class' => $this->transport_class?->value,
            'transport_class_label' => $this->transport_class?->getLabel(),
            'fio' => $this->fio,
            'phone' => $this->phone,
            'comment' => $this->comment,
            'payment_type' => $this->payment_type,
            'payment_card' => $this->payment_card,
            'payment_holder_name' => $this->payment_holder_name,
            'payment_valid_until' => $this->payment_valid_until,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            'from_city' => [
                'id' => $this->fromCity?->id,
                'name' => $this->fromCity?->name,
            ],
            'to_city' => [
                'id' => $this->toCity?->id,
                'name' => $this->toCity?->name,
            ],
        ];
    }
}