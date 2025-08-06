<?php

namespace App\Http\Controllers\Api;

use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Http\Resources\ReviewResource;

class HotelController extends Controller
{
    public function getHotels(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $search = trim(mb_strtolower($search));

        $hotels = Hotel::query()
            ->with(['country', 'city', 'facilities', 'attachments'])
            ->when($search, function($query) use ($search) {
                $query->where(function($q) use ($search) {
                    $q
                        ->whereRaw('LOWER(name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(company_name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(description) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%$search%"]);
                });
            })
            ->get();

        return response()->json(['data' => HotelResource::collection($hotels)]);
    }

    public function getHotelOthers(Request $request): JsonResponse
    {
        $hotelId = $request->route('id');

        $hotel = Hotel::query()
            ->with(['country', 'city', 'facilities', 'attachments'])
            ->whereNot('id', $hotelId)
            ->orderByDesc('rate')
            ->limit(10)
            ->get();

        return response()->json(['data' => HotelResource::collection($hotel)]);
    }

    public function getHotel($hotelId): JsonResponse
    {
        $hotel = Hotel::query()
            ->with(['country', 'city', 'facilities', 'attachments', 'reviews'])
            ->findOrFail($hotelId);

        return response()->json(['data' => HotelResource::make($hotel)]);
    }

    public function getRecommendedHotels(): JsonResponse
    {
        $hotels = Hotel::query()
            ->with(['country', 'city', 'facilities', 'attachments'])
            ->whereHas('recommendedHotels')
            ->get();

        return response()->json(['data' => HotelResource::collection($hotels)]);
    }

    public function storeReview($hotelId, Request $request): JsonResponse
    {
        /** @var Hotel $hotel */
        $hotel = Hotel::query()->findOrFail($hotelId);

        $request->validate([
            'name' => 'nullable|string',
            'hotel' => 'nullable|string|email',
            'rate' => 'required|integer|between:1,5',
            'comment' => 'required|string',
        ]);

        $review = $hotel->reviews()->create([
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'rate' => $request->get('rate'),
            'comment' => $request->get('comment'),
            'user_id' => auth()->user()?->id,
        ]);

        return response()->json(['data' => ReviewResource::make($review)]);
    }
}
