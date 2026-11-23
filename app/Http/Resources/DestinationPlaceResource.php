<?php

namespace App\Http\Resources;

use App\Models\DestinationPlace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DestinationPlace
 */
class DestinationPlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->categoryEnum();

        // Only link to a published page; a draft/removed target must not become a dead link.
        $target = $this->linkDestination;
        $target = $target && $target->is_published ? $target : null;

        $targetCountrySlug = null;
        if ($target) {
            $targetCountrySlug = $target->parent_id === null ? $target->slug : $target->parent?->slug;
        }

        return [
            'id' => $this->id,
            'category' => $category->value,
            'category_title' => $category->heading(),
            'title' => $this->title,
            'description' => $this->description,
            'photo' => $this->photo ? asset('storage/'.$this->photo) : null,
            'link_slug' => $target?->slug,
            'link_country_slug' => $targetCountrySlug,
            'order' => $this->order,
        ];
    }
}
