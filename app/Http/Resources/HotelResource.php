<?php

namespace App\Http\Resources;

use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Hotel
 */
class HotelResource extends JsonResource
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
            'email' => $this->email,
            'contract_number' => $this->contract_number,
            'contract_date' => $this->contract_date?->format('Y-m-d'),
            'country_id' => $this->country_id,
            'city_id' => $this->city_id,
            'booking_cancellation_days' => $this->booking_cancellation_days,
            'inn' => $this->inn,
            'company_name' => $this->company_name,
            'address' => $this->address,
            'rate' => $this->rate,
            'price' => $this->getPrice(),
            'website_price' => $this->website_price,
            'photo' => $this->getPhoto(),
            'photos' => $this->getPhotos(),
            'description' => $this->description,
            'comment' => $this->comment,
            'position' => $this->getPosition(),

            'phone' => $this->getPhone(),
            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'room_types' => $this->getRoomTypes(),
        ];
    }

    private function getPhotos(): array
    {
        return $this->attachments->map(function ($attachment) {
            return $attachment->getUrl();
        })->filter()->values()->all();
    }

    private function getPhoto(): ?string
    {
        return $this->attachments->first()?->getUrl();
    }

    private function getPrice(): ?float
    {
        $seasonType = ExpenseService::getSeasonType($this->resource, now());

        /** @var HotelRoomType $hotelRoomType */
        $hotelRoomType = $this->roomTypes()->where('season_type', $seasonType)->first();

        return $hotelRoomType?->price_foreign;
    }

    private function getPhone()
    {
        return $this->phones->first()?->phone_number;
    }

    private function getPosition(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [(float) $this->longitude, (float) $this->latitude];
        }
        
        return null;
    }

    private function getRoomTypes(): array
    {
        if (!$this->relationLoaded('roomTypes')) {
            return [];
        }

        // Group room types by unique room type name
        $groupedRoomTypes = $this->roomTypes
            ->load('roomType')
            ->groupBy('roomType.name')
            ->map(function ($hotelRoomTypes, $roomTypeName) {
                // Get the first room type for basic info
                $firstRoomType = $hotelRoomTypes->first();
                
                return [
                    'name' => $roomTypeName,
                    'picture' => $firstRoomType->roomType?->picture ? asset('storage/' . $firstRoomType->roomType->picture) : null,
                    'description' => $firstRoomType->roomType?->description,
                ];
            })
            ->values()
            ->toArray();

        return $groupedRoomTypes;
    }
}
