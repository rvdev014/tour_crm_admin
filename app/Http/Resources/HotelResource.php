<?php

namespace App\Http\Resources;

use App\Models\Hotel;
use App\Models\Group;
use Illuminate\Http\Request;
use App\Models\HotelRoomType;
use App\Services\ExpenseService;
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
            'price' => $this->getPrice(false),
            'price_usd' => $this->getPrice(),
            'website_price' => $this->website_price,
            'photo' => $this->getPhoto(),
            'photos' => $this->getPhotos(),
            'description' => $this->description,
            'comment' => $this->comment,
            'position' => $this->getPosition(),

            'phone' => $this->getPhone(),
            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'rooms' => $this->getRooms(),
        ];
    }

    private function getPhotos(): array
    {
        return $this->attachments->map(function($attachment) {
            return $attachment->getUrl();
        })->filter()->values()->all();
    }

    private function getPhoto(): ?string
    {
        return $this->attachments->first()?->getUrl();
    }

    private function getPrice($isUsd = true): ?float
    {
        $period = ExpenseService::getHotelPeriod($this->resource, now());

        /** @var HotelRoomType $hotelRoomType */
        $hotelRoomType = $this->roomTypes()
            ->where('hotel_period_id', $period->id)
            ->first();

        /** @var Group $group */
        $group = Group::query()->where('name', 'website')->first();
        if (!$group) {
            throw new \Exception('Group "website" not found');
        }

        $price = $hotelRoomType?->getPriceByGroup($group);

        $currencyUsd = ExpenseService::getUsdToUzsCurrency();
        if ($isUsd) {
            return round($price / ($currencyUsd?->rate ?? 1), 2);
        }
        
        return $price;
    }

    private function getPhone()
    {
        return $this->phones->first()?->phone_number;
    }

    private function getPosition(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [(float)$this->latitude, (float)$this->longitude];
        }

        return null;
    }

    private function getRooms(): array
    {
        if (!$this->relationLoaded('roomTypes')) {
            return [];
        }

        // Group room types by unique room type name
        $groupedRoomTypes = $this->roomTypes
            ->load('roomType')
            ->groupBy('roomType.name')
            ->map(function($hotelRoomTypes, $roomTypeName) {
                // Get the first room type for basic info
                /** @var HotelRoomType $firstRoomType */
                $firstRoomType = $hotelRoomTypes->first();
                $roomType = $firstRoomType->roomType;

                /** @var Group $group */
                $group = Group::query()->where('name', 'website')->firstOrFail();
                $price = $firstRoomType->getPriceByGroup($group);
                $currencyUsd = ExpenseService::getUsdToUzsCurrency();

                return [
                    'id' => $firstRoomType->id,
                    'room_type_id' => $roomType->id,
                    'name' => $roomTypeName,
                    'picture' => $roomType?->picture ? asset('storage/' . $roomType->picture) : null,
                    'description' => $roomType?->description,
                    'price' => $price,
                    'price_usd' => round($price / ($currencyUsd?->rate ?? 1), 2)
                ];
            })
            ->values()
            ->toArray();

        return $groupedRoomTypes;
    }
}
