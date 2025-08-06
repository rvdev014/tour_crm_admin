<?php

namespace App\Http\Resources;

use App\Models\WebTourAccommodation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebTourAccommodation
 */
class WebTourAccommodationResource extends JsonResource
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
            'header' => $this->header,
            'description' => $this->description,
            'days' => $this->days,
            'created_at' => $this->created_at,
        ];
    }
}
