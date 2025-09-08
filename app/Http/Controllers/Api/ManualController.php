<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransportClass;
use App\Enums\TransferRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Http\Resources\HotelResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\TransferRequestResource;
use App\Http\Resources\TransportClassResource;
use App\Http\Resources\WebTourResource;
use App\Models\Banner;
use App\Models\City;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\Tour;
use App\Models\TransferRequest;
use App\Models\TransportClass as TransportClassModel;
use App\Models\WebTour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManualController extends Controller
{
    public function getTours(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $search = trim(mb_strtolower($search));

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
            ->paginate(10);

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
                'accommodations',
                'packagesIncluded',
                'packagesNotIncluded',
                'currentPrice',
                'reviews',
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
        $transportClasses = TransportClassModel::query()->get();
        return response()->json(['data' => TransportClassResource::collection($transportClasses)]);
    }

    public function storeTransferRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_city_id' => 'required|exists:cities,id',
            'to_city_id' => 'required|exists:cities,id|different:from_city_id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'passengers' => 'required|integer|min:1|max:50',
            'class_auto' => 'nullable|string|in:economy,business,premium',
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:255',
            'comments' => 'nullable|string|max:1000',
            'card_number' => 'nullable|string|max:255',
            'cardholder_name' => 'nullable|string|max:255',
            'valid_until' => 'nullable|string|max:255',
        ]);

        // Combine date and time into datetime
        $dateTime = $validated['date'] . ' ' . $validated['time'];

        // Map transport class
        $transportClass = match($validated['class_auto'] ?? null) {
            'business' => 2,
            'premium' => 4,
            default => 1, // economy
        };

        $transferRequest = TransferRequest::create([
            'status' => TransferRequestStatus::Created,
            'user_id' => $request->user()?->id,
            'from_city_id' => $validated['from_city_id'],
            'to_city_id' => $validated['to_city_id'],
            'date_time' => $dateTime,
            'passengers_count' => $validated['passengers'],
            'transport_class' => $transportClass,
            'fio' => $validated['full_name'],
            'phone' => $validated['phone_number'],
            'comment' => $validated['comments'],
            'payment_card' => $validated['card_number'],
            'payment_holder_name' => $validated['cardholder_name'],
            'payment_valid_until' => $validated['valid_until'],
        ]);

        return response()->json([
            'message' => 'Transfer request created successfully',
            'data' => new TransferRequestResource($transferRequest->load(['fromCity', 'toCity']))
        ], 201);
    }

    public function updateTransferRequest(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|integer|in:1,2,3',
        ]);

        $transferRequest = TransferRequest::findOrFail($id);
        $transferRequest->update([
            'status' => $validated['status'],
        ]);

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
}
