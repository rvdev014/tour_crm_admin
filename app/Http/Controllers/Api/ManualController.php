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
use App\Models\WebTour;
use Illuminate\Http\JsonResponse;

class ManualController extends Controller
{
    public function getTours(): JsonResponse
    {
        $webTours = WebTour::query()
//            ->withCount([
//                'days'
//            ])
            ->with([
                'days',
                'currentPrice',
            ])
            ->get();

        return response()->json(['data' => WebTourResource::collection($webTours)]);
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
