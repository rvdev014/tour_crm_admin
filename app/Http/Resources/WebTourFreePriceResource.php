<?php

namespace App\Http\Resources;

use App\Models\WebTourFreePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebTourFreePrice
 */
class WebTourFreePriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'web_tour_id' => $this->web_tour_id,
            'pax_from' => $this->pax_from,
            'pax_to' => $this->pax_to,
            'price' => $this->price,
            'price_usd' => $this->price_usd,
            'price_uzs' => $this->price_uzs,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
