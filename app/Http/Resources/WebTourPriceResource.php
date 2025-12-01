<?php

namespace App\Http\Resources;

use App\Models\WebTourPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebTourPrice
 */
class WebTourPriceResource extends JsonResource
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
            'web_tour_id' => $this->web_tour_id,
            'from_date' => $this->from_date,
            'to_date' => $this->to_date,
            'deadline' => $this->deadline,
            'status' => $this->status,
            'price' => $this->price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}