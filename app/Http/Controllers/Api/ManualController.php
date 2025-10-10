<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ReviewResource;
use App\Models\City;
use App\Models\Banner;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Service;
use App\Models\Tour;
use App\Models\WebTour;
use App\Models\RoomType;
use Illuminate\Http\Request;
use App\Models\TransportClass;
use App\Models\TransferRequest;
use Illuminate\Http\JsonResponse;
use App\Enums\TransferRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\WebTourResource;
use App\Http\Resources\TransportClassResource;
use App\Http\Resources\TransferRequestResource;

class ManualController extends Controller
{
    public function getTours(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $search = trim(mb_strtolower($search));
        $isPopular = $request->get('is_popular');

        $webTours = WebTour::query()
            ->with([
                'days' => fn($query) => $query->with(['facilities']),
                'currentPrice',
            ])
            ->when($search, function($query) use ($search) {
                $query->where(function($q) use ($search) {
                    $q
                        ->whereRaw('LOWER(name_ru) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(name_en) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(description_ru) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(description_en) LIKE ?', ["%$search%"]);
                });
            })
            ->when($isPopular !== null, function($query) use ($isPopular) {
                $query->where('is_popular', filter_var($isPopular, FILTER_VALIDATE_BOOLEAN));
            })
            ->paginate(15);

        return response()->json([
            'data' => WebTourResource::collection($webTours->items()),
            'pagination' => [
                'current_page' => $webTours->currentPage(),
                'last_page' => $webTours->lastPage(),
                'per_page' => $webTours->perPage(),
                'total' => $webTours->total(),
                'from' => $webTours->firstItem(),
                'to' => $webTours->lastItem(),
                'has_next_page' => $webTours->hasMorePages(),
                'has_previous_page' => $webTours->currentPage() > 1,
            ]
        ]);
    }

    public function getTour($tourId): JsonResponse
    {
        $webTour = WebTour::query()
            ->with([
                'days' => fn($query) => $query->with(['facilities']),
                'accommodations' => fn($query) => $query->with([
                    'hotels' => fn($query) => $query->with(['facilities'])
                ]),
                'packagesIncluded',
                'packagesNotIncluded',
                'currentPrice',
                'activeReviews',
                'prices',
            ])
            ->findOrFail($tourId);

        return response()->json(['data' => WebTourResource::make($webTour)]);
    }

    public function getSimilarTours($tourId): JsonResponse
    {
        /** @var WebTour $webTour */
        $webTour = WebTour::query()->findOrFail($tourId);

        $similarTours = $webTour->similarTours()
            ->with([
                'days' => fn($query) => $query->with(['facilities']),
                'currentPrice',
            ])
            ->get();

        return response()->json(['data' => WebTourResource::collection($similarTours)]);
    }

    public function storeReview($webTourId, Request $request): JsonResponse
    {
        /** @var Hotel $hotel */
        $hotel = WebTour::query()->findOrFail($webTourId);

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

    public function getBanners(): JsonResponse
    {
        $banners = Banner::query()->get();
        return response()->json(['data' => BannerResource::collection($banners)]);
    }

    public function getServices(): JsonResponse
    {
        $banners = Service::query()->get();
        return response()->json(['data' => ServiceResource::collection($banners)]);
    }

    public function getCountries(): JsonResponse
    {
        $countries = Country::query()->get();
        return response()->json(['data' => $countries]);
    }

    public function getCities(): JsonResponse
    {
        $cities = City::query()->get();
        return response()->json(['data' => $cities]);
    }

    public function getRoomTypes(): JsonResponse
    {
        $roomTypes = RoomType::query()->select('id', 'name')->get();
        return response()->json(['data' => $roomTypes]);
    }

    public function getTransportClasses(): JsonResponse
    {
        $transportClasses = TransportClass::query()->orderBy('price_per_km')->get();
        return response()->json(['data' => TransportClassResource::collection($transportClasses)]);
    }

    public function storeTransferRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string',
            'to' => 'required|string|different:from',
            'from_coords' => 'required|nullable|string',
            'to_coords' => 'required|nullable|string',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'passengers' => 'required|integer|min:1|max:50',
            'class_auto' => 'nullable|string|in:economy,business,premium',
            'full_name' => 'string|max:255',
            'phone_number' => 'string|max:255',
            'comments' => 'nullable|string|max:1000',
            'distance' => 'nullable|numeric|min:0',
        ]);

        // Combine date and time into datetime
        $dateTime = $validated['date'] . ' ' . $validated['time'];

        // Map transport class
        $transportClass = match ($validated['class_auto'] ?? null) {
            'business' => 2,
            'premium'  => 4,
            default    => 1, // economy
        };

        $transferRequest = TransferRequest::query()->create([
            'status' => TransferRequestStatus::Created,
            'user_id' => $request->user()?->id,
            'from' => $validated['from'],
            'to' => $validated['to'],
            'date_time' => $dateTime,
            'distance' => $validated['distance'] ?? null,
            'from_coords' => $validated['from_coords'] ?? null,
            'to_coords' => $validated['to_coords'] ?? null,
            'passengers_count' => $validated['passengers'] ?? null,
            'transport_class' => $transportClass,
            'fio' => $validated['full_name'] ?? null,
            'phone' => $validated['phone_number'] ?? null,
            'comment' => $validated['comments'] ?? null,
        ]);

        return response()->json([
            'message' => 'Transfer request created successfully',
            'data' => new TransferRequestResource($transferRequest->load(['fromCity', 'toCity']))
        ], 201);
    }

    public function updateTransferRequest(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'transport_class_id' => 'nullable|exists:transport_classes,id',
        ]);

        $transferRequest = TransferRequest::query()->findOrFail($id);
        $transferRequest->update(array_filter([
            'status' => TransferRequestStatus::TransportType,
            'transport_class_id' => $validated['transport_class_id'] ?? null,
        ]));

        return response()->json([
            'message' => 'Transfer request updated successfully',
            'data' => new TransferRequestResource($transferRequest->load(['fromCity', 'toCity']))
        ]);
    }

    public function getUnbookedTransferRequest(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['data' => false]);
        }

        $unbookedRequest = TransferRequest::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', TransferRequestStatus::Booked)
            ->with(['fromCity', 'toCity'])
            ->orderByDesc('created_at')
            ->first();

        if (!$unbookedRequest) {
            return response()->json(['data' => false]);
        }

        return response()->json([
            'data' => new TransferRequestResource($unbookedRequest)
        ]);
    }

    public function bookTransferRequest(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'terminal_name' => 'nullable|string|max:255',
            'activate_flight_tracking' => 'nullable|boolean',
            'fio' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'text_on_sign' => 'nullable|string|max:255',
            'is_sample_baggage' => 'nullable|boolean',
            'baggage_count' => 'nullable|integer|min:0',
            'comment' => 'nullable|string|max:1000',
        ]);

        $transferRequest = TransferRequest::findOrFail($id);

        $transferRequest->update([
            'status' => 3, // Booked status
            'terminal_name' => $validated['terminal_name'] ?? null,
            'activate_flight_tracking' => $validated['activate_flight_tracking'] ?? false,
            'fio' => $validated['fio'],
            'phone' => $validated['phone'],
            'text_on_sign' => $validated['text_on_sign'] ?? null,
            'is_sample_baggage' => $validated['is_sample_baggage'] ?? false,
            'baggage_count' => $validated['baggage_count'] ?? null,
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Transfer request booked successfully',
            'data' => new TransferRequestResource($transferRequest->load(['fromCity', 'toCity']))
        ]);
    }
}
