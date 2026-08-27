<?php

namespace App\Http\Resources;

use App\Models\DestinationSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DestinationSection
 */
class DestinationSectionResource extends JsonResource
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
            'title' => $this->title,
            'anchor' => $this->anchor,
            'photo' => $this->photo ? asset('storage/'.$this->photo) : null,
            'content' => $this->content,
            'order' => $this->order,
        ];
    }
}
