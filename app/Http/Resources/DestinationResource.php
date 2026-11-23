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
            'places' => DestinationPlaceResource::collection($this->whenLoaded('places')),
            'dishes' => DestinationDishResource::collection($this->whenLoaded('dishes')),
            'cuisine_intro' => $this->cuisine_intro,
            'seasons' => $this->seasonsPayload(),
            'map_photo' => $this->map_photo ? asset('storage/'.$this->map_photo) : null,
            'tours' => WebTourResource::collection($this->whenLoaded('tours')),
        ];
    }

    /**
     * The seasons block, or null when nothing is filled in so the website can hide it.
     *
     * @return array<string, string|null>|null
     */
    private function seasonsPayload(): ?array
    {
        $seasons = [
            'intro' => $this->seasons_intro,
            'winter' => $this->season_winter,
            'spring' => $this->season_spring,
            'summer' => $this->season_summer,
            'autumn' => $this->season_autumn,
        ];

        return array_filter($seasons) === [] ? null : $seasons;
    }
}
