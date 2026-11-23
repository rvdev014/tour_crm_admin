<?php

namespace App\Http\Resources;

use App\Models\DestinationDish;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DestinationDish
 */
class DestinationDishResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'photo' => $this->photo ? asset('storage/'.$this->photo) : null,
            'order' => $this->order,
        ];
    }
}
