<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainRequestRequest;
use App\Enums\TrainWebClass;
use App\Enums\WagonClass;
use App\Models\TrainRequest;
use App\Models\TrainWebPrice;
use Illuminate\Http\JsonResponse;

class TrainController extends Controller
{
    public function storeTrainRequest(StoreTrainRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // A guaranteed ticket takes its route, class and price from our own price list, never from
        // what the client sent, so a tampered payload can't claim a cheaper fare.
        $guaranteed = [];
        $webPrice = isset($validated['train_web_price_id'])
            ? TrainWebPrice::with(['train', 'fromCity', 'toCity'])->find($validated['train_web_price_id'])
            : null;

        if ($webPrice) {
            $class = TrainWebClass::from($validated['train_class']);
            $price = $webPrice->priceFor($class);

            if ($price === null) {
                return response()->json([
                    'message' => 'This class is not available on the selected train.',
                    'errors' => ['train_class' => ['This class is not available on the selected train.']],
                ], 422);
            }

            $guaranteed = [
                'from' => $webPrice->fromCity->name,
                'to' => $webPrice->toCity->name,
                'wagon_class' => WagonClass::Seated->value,
                'is_guaranteed' => true,
                'train_web_price_id' => $webPrice->id,
                'train_class' => $class->value,
                'price_uzs' => $price,
            ];
        }

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
            ...$guaranteed,
        ]);

        return response()->json([
            'data' => $trainRequest,
            'message' => 'Train request created successfully',
        ], 201);
    }
}
