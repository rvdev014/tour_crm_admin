<?php

namespace App\Http\Resources;

use App\Models\HotelReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HotelReview
 */
class HotelReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'user_id' => $this->user_id,
            'hotel_id' => $this->hotel_id,
            'comment' => $this->comment,
            'rate' => $this->rate,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
}
