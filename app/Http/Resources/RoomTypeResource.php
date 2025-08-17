<?php

namespace App\Http\Resources;

use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoomType
 */
class RoomTypeResource extends JsonResource
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
            'picture' => $this->getPictureUrl(),
            'description' => $this->description,
        ];
    }

    private function getPictureUrl(): ?string
    {
        if (!$this->picture) {
            return null;
        }

        // Return asset URL for the picture
        return asset('storage/' . $this->picture);
    }
}