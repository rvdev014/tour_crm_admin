<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransportClass;
use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Http\Resources\HotelResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\TransferRequestResource;
use App\Http\Resources\WebTourResource;
use App\Models\Banner;
use App\Models\City;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Service;
use App\Models\Tour;
use App\Models\TransferRequest;
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
            ])
            ->findOrFail($tourId);

        return response()->json(['data' => WebTourResource::make($webTour)]);
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

    public function storeTransferRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_city_id' => 'required|exists:cities,id',
            'to_city_id' => 'required|exists:cities,id|different:from_city_id',
            'date_time' => 'required|date|after:now',
            'passengers_count' => 'required|integer|min:1|max:50',
            'transport_class' => 'nullable|integer|in:' . implode(',', array_column(TransportClass::cases(), 'value')),
            'fio' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'payment_type' => 'nullable|string|max:255',
            'payment_card' => 'nullable|string|max:255',
            'payment_holder_name' => 'nullable|string|max:255',
            'payment_valid_until' => 'nullable|string|max:255',
        ]);

        $transferRequest = TransferRequest::create($validated);

        return response()->json([
            'message' => 'Transfer request created successfully',
            'data' => new TransferRequestResource($transferRequest->load(['fromCity', 'toCity']))
        ], 201);
    }
}
