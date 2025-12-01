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
            'status' => $this->status,
            'from' => $this->from,
            'to' => $this->to,
            'distance' => $this->distance,
            'total_fare' => $this->total_fare,
            'from_coords' => $this->from_coords,
            'to_coords' => $this->to_coords,
            'date_time' => $this->date_time,
            'passengers_count' => $this->passengers_count,
            'transport_class' => $this->transportClass,
            'fio' => $this->fio,
            'phone' => $this->phone,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
