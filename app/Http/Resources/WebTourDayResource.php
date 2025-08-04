<?php

namespace App\Http\Resources;

use App\Models\WebTourDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebTourDay
 */
class WebTourDayResource extends JsonResource
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
            'day_number' => $this->day_number,
            'place_name' => $this->place_name,
            'date' => $this->date,
            'photo' => $this->photo ? asset('storage/' . $this->photo) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
        ];
    }
}
