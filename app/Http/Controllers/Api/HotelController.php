<?php

namespace App\Http\Controllers\Api;

use App\Models\Hotel;
use App\Models\HotelRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Http\Resources\ReviewResource;
use App\Http\Requests\StoreHotelRequestRequest;

class HotelController extends Controller
{
    public function getHotels(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $search = trim(mb_strtolower($search));

        $hotels = Hotel::query()
            ->with(['country', 'city', 'facilities', 'attachments', 'roomTypes.roomType'])
            ->when($search, function($query) use ($search) {
                $query->where(function($q) use ($search) {
                    $q
                        ->whereRaw('LOWER(name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(company_name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(description_en) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(description_ru) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%$search%"]);
                });
            })
            ->paginate(10);

        return response()->json([
            'data' => HotelResource::collection($hotels->items()),
            'pagination' => [
                'current_page' => $hotels->currentPage(),
                'last_page' => $hotels->lastPage(),
                'per_page' => $hotels->perPage(),
                'total' => $hotels->total(),
                'from' => $hotels->firstItem(),
                'to' => $hotels->lastItem(),
                'has_next_page' => $hotels->hasMorePages(),
                'has_previous_page' => $hotels->currentPage() > 1,
            ]
        ]);
    }

    public function getHotelOthers(Request $request): JsonResponse
    {
        $hotelId = $request->route('id');

        $hotel = Hotel::query()
            ->with(['country', 'city', 'facilities', 'attachments', 'roomTypes.roomType'])
            ->whereNot('id', $hotelId)
            ->orderByDesc('rate')
            ->limit(10)
            ->get();

        return response()->json(['data' => HotelResource::collection($hotel)]);
    }

    public function getHotel($hotelId): JsonResponse
    {
        $hotel = Hotel::query()
            ->with(['country', 'city', 'facilities', 'attachments', 'activeReviews', 'roomTypes.roomType'])
            ->findOrFail($hotelId);

        return response()->json(['data' => HotelResource::make($hotel)]);
    }

    public function getRecommendedHotels(): JsonResponse
    {
        $hotels = Hotel::query()
            ->with(['country', 'city', 'facilities', 'attachments', 'roomTypes.roomType'])
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
            'is_active' => false,
        ]);

        return response()->json(['data' => ReviewResource::make($review)]);
    }

    public function storeHotelRequest(StoreHotelRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $hotelRequest = HotelRequest::create([
            'checkin_time' => $validated['check_in_date'],
            'checkout_time' => $validated['check_out_date'],
            'room_type_id' => $validated['room_type'],
            'hotel_id' => $validated['hotel_id'],
            'user_id' => auth()->user()?->id,
            'comment' => $validated['comments'] ?? null,
        ]);

        return response()->json([
            'data' => $hotelRequest,
            'message' => 'Hotel request created successfully'
        ], 201);
    }
}
