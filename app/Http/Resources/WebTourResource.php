<?php

namespace App\Http\Resources;

use App\Enums\WebTourPriceType;
use App\Models\WebTour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebTour
 */
class WebTourResource extends JsonResource
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
            'type' => $this->type,
            'price_type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'current_price' => $this->getCurrentPrice(),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'photo' => $this->photo ? asset('storage/'.$this->photo) : null,
            'photos' => collect($this->photos ?? [])->map(fn ($p) => asset('storage/'.$p))->values()->all(),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'days' => WebTourDayResource::collection($this->whenLoaded('days')),
            'accommodations' => WebTourAccommodationResource::collection($this->whenLoaded('accommodations')),
            'packagesIncluded' => PackageResource::collection($this->whenLoaded('packagesIncluded')),
            'packagesNotIncluded' => PackageResource::collection($this->whenLoaded('packagesNotIncluded')),
            'reviews' => ReviewResource::collection($this->whenLoaded('activeReviews')),
            'prices' => WebTourPriceResource::collection($this->whenLoaded('prices')),
            'free_prices' => WebTourFreePriceResource::collection($this->whenLoaded('freePrices')),
            'per_person_prices' => WebTourPerPersonPriceResource::collection($this->whenLoaded('perPersonPrices')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
        ];
    }

    public function getCurrentPrice()
    {
        if ($this->type == WebTourPriceType::Free->value) {
            $freePrice = $this->freePrices()->orderBy('price')->first();

            return $freePrice;
        }

        if ($this->type == WebTourPriceType::PerPerson->value) {
            return $this->perPersonPrices()->orderBy('price')->first();
        }

        // currentPrice filters by active date range; fall back to the cheapest upcoming price
        if ($this->currentPrice) {
            return $this->currentPrice;
        }

        return $this->relationLoaded('prices')
            ? $this->prices->sortBy('price')->first()
            : $this->prices()->orderBy('price')->first();
    }
}
