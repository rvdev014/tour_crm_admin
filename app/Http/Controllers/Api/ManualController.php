<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;

class ManualController extends Controller
{
    public function getHotels(): JsonResponse
    {
        try {
            $hotels = Hotel::query()
                ->with(['country', 'city'])
                ->get();
            return response()->json(['data' => $hotels]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getCountries(): JsonResponse
    {
        try {
            $countries = Country::query()
                ->get();
            return response()->json(['data' => $countries]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getCities(): JsonResponse
    {
        try {
            $cities = City::query()
                ->get();
            return response()->json(['data' => $cities]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
