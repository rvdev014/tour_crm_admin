<?php

namespace App\Services;

use App\Enums\TransferRequestStatus;
use App\Models\TransferRequest;
use App\Models\TransportClass;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public static function getUnbookedRequest($user): ?TransferRequest
    {
        /** @var TransferRequest $unbookedRequest */
        $unbookedRequest = TransferRequest::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', TransferRequestStatus::Booked)
            ->with(['fromCity', 'toCity'])
            ->orderBy('created_at')
            ->first();

        return $unbookedRequest;
    }

    public static function storeMultipleRequests(TransferRequest $transferRequest, TransportClass $transportClass): void
    {
        DB::transaction(function () use ($transferRequest, $transportClass) {
            $capacity = $transportClass->passenger_capacity;
            $total = $transferRequest->passengers_count;

            // сколько полных частей (включая первую) и остаток
            $fullParts = intdiv($total, $capacity);
            $remainder = $total % $capacity;

            // обновим исходную заявку — первая часть (capacity пассажиров)
            $transferRequest->update([
                'status' => TransferRequestStatus::TransportType,
                'transport_class_id' => $transportClass->id,
                'passengers_count' => $capacity,
            ]);

            // возьмём базовые атрибуты для копирования
            $base = $transferRequest->replicate()->toArray(); // без id, timestamps
            $base['parent_id'] = $transferRequest->id;
            $base['transport_class_id'] = $transportClass->id;
            $base['status'] = TransferRequestStatus::TransportType;

            // создаём (fullParts - 1) полных заявок
            for ($i = 1; $i < $fullParts; $i++) {
                $base['passengers_count'] = $capacity;
                TransferRequest::query()->create($base);
            }

            // если есть остаток — создаём ещё одну заявку с remainder
            if ($remainder > 0) {
                $base['passengers_count'] = $remainder;
                TransferRequest::query()->create($base);
            }
        });
    }
}
