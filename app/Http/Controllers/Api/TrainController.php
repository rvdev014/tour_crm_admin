<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainRequestRequest;
use App\Models\TrainRequest;
use Illuminate\Http\JsonResponse;

class TrainController extends Controller
{
    public function storeTrainRequest(StoreTrainRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $trainRequest = TrainRequest::create([
            'user_id' => $user?->id,
            'from' => $validated['from'],
            'to' => $validated['to'],
            'departure_date' => $validated['departure_date'],
            'return_date' => $validated['return_date'] ?? null,
            'passengers_count' => $validated['passengers_count'],
            'wagon_class' => $validated['wagon_class'] ?? null,
            'phone' => $validated['phone'] ?? $user?->phone,
            'email' => $validated['email'] ?? $user?->email,
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'data' => $trainRequest,
            'message' => 'Train request created successfully',
        ], 201);
    }
}
