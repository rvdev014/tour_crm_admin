<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Http\Resources\HotelReviewResource;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function getHotels(): JsonResponse
    {
        $hotels = Hotel::query()
            ->with(['country', 'city', 'facilities', 'attachments'])
            ->get();

        return response()->json(['data' => HotelResource::collection($hotels)]);
    }

    public function getHotel($hotelId): JsonResponse
    {
        $hotel = Hotel::query()
            ->with(['country', 'city', 'facilities', 'attachments'])
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
            'rate' => 'required|float',
            'comment' => 'required|string',
        ]);

        $review = $hotel->reviews()->create([
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'rate' => $request->get('rate'),
            'comment' => $request->get('comment'),
            'user_id' => auth()->user()?->id,
        ]);

        return response()->json(['data' => HotelReviewResource::make($review)]);
    }
}
