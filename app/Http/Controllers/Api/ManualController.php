<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Http\Resources\HotelResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\WebTourResource;
use App\Models\Banner;
use App\Models\City;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Service;
use App\Models\Tour;
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
            ->get();

        return response()->json(['data' => WebTourResource::collection($webTours)]);
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
}
