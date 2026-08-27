<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFlightRequestRequest;
use App\Models\FlightRequest;
use Illuminate\Http\JsonResponse;

class FlightController extends Controller
{
    public function storeFlightRequest(StoreFlightRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $flightRequest = FlightRequest::create([
            'user_id' => $user?->id,
            'from' => $validated['from'],
            'to' => $validated['to'],
            'departure_date' => $validated['departure_date'],
            'return_date' => $validated['return_date'] ?? null,
            'passengers_count' => $validated['passengers_count'],
            'cabin_class' => $validated['cabin_class'] ?? null,
            'phone' => $validated['phone'] ?? $user?->phone,
            'email' => $validated['email'] ?? $user?->email,
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'data' => $flightRequest,
            'message' => 'Flight request created successfully',
        ], 201);
    }
}
