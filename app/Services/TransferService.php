<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Enums\TransferRequestStatus;
use App\Mail\TransferReminderMail;
use App\Mail\TransferRequestConfirmedMail;
use App\Models\Transfer;
use App\Models\TransferRequest;
use App\Models\TransportClass;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TransferService
{
    public static function getUnbookedRequest($user): ?TransferRequest
    {
        /** @var TransferRequest $unbookedRequest */
        $unbookedRequest = TransferRequest::query()
            ->where('user_id', $user->id)
            ->where('status', '<', TransferRequestStatus::Booked->value)
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

    /**
     * @throws Throwable
     */
    public static function acceptRequest(TransferRequest $transferRequest): Transfer
    {
        try {
            DB::beginTransaction();

            // Update status to confirmed
            $transferRequest->update(['status' => TransferRequestStatus::Accepted]);

            // Create transfer from the request
            /** @var Transfer $transfer */
            $transfer = Transfer::query()->updateOrCreate(
                ['transfer_request_id' => $transferRequest->id],
                [
                    'from' => $transferRequest->from,
                    'to' => $transferRequest->to,
                    'date_time' => $transferRequest->date_time,
                    'pax' => $transferRequest->passengers_count,
                    'route' => $transferRequest->from . ' - ' . $transferRequest->to,
                    'passenger' => $transferRequest->fio,
                    'comment' => $transferRequest->comment,
//                                'transport_type' => \App\Enums\TransportType::Sedan,
//                                'transport_comfort_level' => \App\Enums\TransportComfortLevel::Standard,
                    'nameplate' => $transferRequest->text_on_sign,
                    'requested_by' => $transferRequest->fio,
                    'status' => ExpenseStatus::New,
                    'location_details' => $transferRequest->terminal_name,
                    'sell_price' => $transferRequest->total_fare,
                    'transfer_request_id' => $transferRequest->id,
//                                'company_id' => 1, // Default company
                ]
            );

            // Send confirmation email if user exists
            if ($transferRequest->user?->email) {
                Mail::to($transferRequest->user->email)->send(new TransferRequestConfirmedMail($transferRequest, $transfer));
            }

            DB::commit();
            return $transfer;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public static function notifyClientsForTransfer(): void
    {
        $nowUtc = Carbon::now('UTC');
        $targetWindowStart = $nowUtc->copy()->addMinutes(119); // ~1 hr 59 min
        $targetWindowEnd = $nowUtc->copy()->addMinutes(121);   // ~2 hr 1 min

        /** @var Collection<Transfer> $transfers */
        $transfers = Transfer::query()
            ->with(['transferRequest.user'])
            ->where('date_time', '>=', $targetWindowStart)
            ->where('date_time', '<=', $targetWindowEnd)
            ->whereNull('user_notified_at')
            ->get();

        foreach ($transfers as $transfer) {
            if (!$transfer->transferRequest->user?->email) {
                continue;
            }

            Mail::to($transfer->transferRequest->user->email)->send(new TransferReminderMail($transfer));
            $transfer->update(['user_notified_at' => now()]);
        }
    }
}
