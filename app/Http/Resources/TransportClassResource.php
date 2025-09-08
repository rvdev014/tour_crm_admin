<?php

namespace App\Http\Resources;

use App\Models\TransportClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportClass
 */
class TransportClassResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'price_per_km' => $this->price_per_km,
            'photo' => $this->photo ? asset('storage/' . $this->photo) : null,
        ];
    }
}