<?php

namespace App\Http\Resources;

use App\Models\WebTourPerPersonPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebTourPerPersonPrice
 */
class WebTourPerPersonPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'web_tour_id' => $this->web_tour_id,
            'pax_count' => $this->pax_count,
            'price' => $this->price,
            'price_usd' => $this->price_usd,
            'price_uzs' => $this->price_uzs,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
