<?php

namespace App\Http\Resources;

use App\Models\Destination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Destination
 */
class DestinationResource extends JsonResource
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
            'slug' => $this->slug,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'photo' => $this->photo ? asset('storage/'.$this->photo) : null,
            'photos' => collect($this->photos ?? [])->map(fn ($p) => asset('storage/'.$p))->values()->all(),
            'is_featured' => $this->is_featured,
            'order' => $this->order,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,

            'parent' => new self($this->whenLoaded('parent')),
            'children' => self::collection($this->whenLoaded('publishedChildren')),
            'sections' => DestinationSectionResource::collection($this->whenLoaded('sections')),
            'tours' => WebTourResource::collection($this->whenLoaded('tours')),
        ];
    }
}
