<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHotelRequestRequest;
use App\Http\Resources\HotelResource;
use App\Http\Resources\ReviewResource;
use App\Models\Hotel;
use App\Models\HotelRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function getHotels(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $search = trim(mb_strtolower($search));

        // Получаем параметры фильтрации
        $cityId = $request->get('city');
        $roomTypes = $request->get('room_types');
        $rate = $request->get('rate');
        $facilityIds = $request->get('facilities'); // Ожидаем массив ID
        $sort = $request->get('sort'); // 'cheapest' или 'most_expensive'

        $query = Hotel::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q
                        ->whereRaw('LOWER(name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(company_name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(description_en) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(description_ru) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%$search%"]);
                });
            });

        if (!empty($cityId)) {
            $query->where('city_id', $cityId);
        }

        if (!empty($roomTypes)) {
            $query->whereHas('roomTypes', fn($q) => $q->whereIn('room_type_id', $roomTypes));
        }

        if (!empty($rate)) {
            $query->where('rate', $rate);
        }

        if (!empty($facilityIds)) {
            $query->whereHas('facilities', fn($q) => $q->whereIn('facilities.id', $facilityIds));
        }

        if (!empty($sort)) {
            $now = now()->format('Y-m-d');
            $sortDirection = $sort === 'cheap' ? 'asc' : 'desc';
            $query
                ->addSelect([
                    'today_price' => function ($subquery) use ($now) {
                        $subquery->select('price')
                            ->from('hotel_room_types')
                            ->join('hotel_periods', function ($join) {
                                $join->on('hotel_periods.id', '=', 'hotel_room_types.hotel_period_id');
                            })
                            ->whereColumn('hotel_room_types.hotel_id', 'hotels.id')
                            ->where('hotel_periods.start_date', '<=', $now)
                            ->where('hotel_periods.end_date', '>=', $now)
                            ->orderBy('price', 'asc')
                            ->limit(1);
                    }
                ])
                ->orderBy('today_price', $sortDirection);
        }

        $hotels = $query
            ->orderByRaw('rate DESC NULLS LAST')
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
