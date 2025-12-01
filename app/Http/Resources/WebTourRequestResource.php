<?php

namespace App\Http\Resources;

use App\Models\WebTourRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebTourRequest
 */
class WebTourRequestResource extends JsonResource
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
            'start_date' => $this->start_date,
            'phone' => $this->phone,
            'citizenship' => $this->citizenship,
            'comment' => $this->comment,
            'travellers_count' => $this->travellers_count,
            'tour_type' => $this->tour_type,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'tour' => new WebTourResource($this->whenLoaded('webTour')),
        ];
    }
}