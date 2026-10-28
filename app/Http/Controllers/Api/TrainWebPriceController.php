<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrainWebPriceResource;
use App\Models\TrainWebPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainWebPriceController extends Controller
{
    /**
     * Cities that have at least one website price, with the cities reachable from each,
     * so the trains page can offer only routes that have a guaranteed price.
     */
    public function cities(): JsonResponse
    {
        $routes = TrainWebPrice::query()->with(['fromCity', 'toCity'])->get();

        $data = $routes->groupBy('from_city_id')->map(fn ($group) => [
            'id' => $group->first()->from_city_id,
            'name' => $group->first()->fromCity->name,
            'to' => $group->pluck('to_city_id')->unique()->values(),
        ])->sortBy('name')->values();

        return response()->json(['data' => $data]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_city_id' => ['required', 'integer'],
            'to_city_id' => ['required', 'integer'],
        ]);

        $prices = TrainWebPrice::query()
            ->with(['train', 'fromCity', 'toCity'])
            ->where('from_city_id', $validated['from_city_id'])
            ->where('to_city_id', $validated['to_city_id'])
            ->orderBy('train_id')
            ->get();

        return response()->json(['data' => TrainWebPriceResource::collection($prices)]);
    }
}
