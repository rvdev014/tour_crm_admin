<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps one priced vehicle option, as built by
 * TransferQuoteService::priceOptions(). $resource is a plain array, not a
 * model — see TransferController::quotes().
 */
class TransferQuoteOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transport_class' => new TransportClassResource($this->resource['transport_class']),
            'max_people' => $this->resource['max_people'],
            'vehicle_count' => $this->resource['vehicle_count'],
            'price_per_vehicle' => $this->resource['price_per_vehicle'],
            'total' => $this->resource['total'],
            'currency' => $this->resource['currency'],
        ];
    }
}
