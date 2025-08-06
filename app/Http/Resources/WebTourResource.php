<?php

namespace App\Http\Resources;

use App\Models\WebTour;
use App\Models\WebTourAccommodation;
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
            'name' => $this->name,
            'description' => $this->description,
            'current_price' => $this->currentPrice,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'photo' => $this->photo ? asset('storage/' . $this->photo) : null,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'days' => WebTourDayResource::collection($this->whenLoaded('days')),
            'accommodations' => WebTourAccommodationResource::collection($this->whenLoaded('accommodations')),
            'packagesIncluded' => PackageResource::collection($this->whenLoaded('packagesIncluded')),
            'packagesNotIncluded' => PackageResource::collection($this->whenLoaded('packagesNotIncluded')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
