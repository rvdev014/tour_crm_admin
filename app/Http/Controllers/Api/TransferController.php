<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferBookingRequest;
use App\Http\Requests\StoreTransferQuoteRequest;
use App\Http\Resources\TransferBookingResource;
use App\Http\Resources\TransferExtraResource;
use App\Http\Resources\TransferQuoteOptionResource;
use App\Models\TransferBooking;
use App\Models\TransferExtra;
use App\Services\TransferQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TransferController extends Controller
{
    public function __construct(private readonly TransferQuoteService $quoteService) {}

    public function getExtras(): JsonResponse
    {
        $extras = TransferExtra::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        return response()->json(['data' => TransferExtraResource::collection($extras)]);
    }

    public function storeQuote(StoreTransferQuoteRequest $request): JsonResponse
    {
        $timezone = $request->header('Timezone') ?: config('app.timezone');

        try {
            $quote = $this->quoteService->quote($request->validated(), $timezone);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'quote_token' => $quote['quote_token'],
                'expires_at' => $quote['expires_at'],
                'distance_km' => $quote['distance_km'],
                'legs' => $quote['legs'],
                'options' => TransferQuoteOptionResource::collection($quote['options']),
            ],
        ]);
    }

    public function storeBooking(StoreTransferBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->quoteService->createBooking($request->validated(), $request->user()?->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Transfer booking created successfully',
            'data' => new TransferBookingResource($booking),
        ], 201);
    }

    public function getBooking(Request $request, string $reference): JsonResponse
    {
        /** @var TransferBooking $booking */
        $booking = TransferBooking::query()
            ->where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->with(['legs.transportClass', 'extras'])
            ->firstOrFail();

        return response()->json(['data' => new TransferBookingResource($booking)]);
    }
}
