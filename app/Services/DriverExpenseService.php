<?php

namespace App\Services;

use App\Enums\DriverExpenseStatus;
use App\Enums\DriverExpenseType;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverExpense;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DriverExpenseService
{
    /** Postgres unique_violation. */
    private const UNIQUE_VIOLATION = '23505';

    /**
     * Record an expense with its receipt photo.
     *
     * Idempotent on $submissionKey: the cabinet form carries a random token, so a retry or double tap on
     * a flaky mobile connection re-sends the SAME token and gets the original expense back instead of a
     * duplicate (and a second photo). The check is the database's unique index, so two simultaneous
     * requests cannot both win.
     *
     * The photo goes to the PRIVATE disk under a generated name; the client's filename is never used.
     *
     * @param  int  $amount  whole sums (UZS)
     */
    public static function add(
        Transfer $transfer,
        Driver $by,
        DriverExpenseType $type,
        int $amount,
        UploadedFile $receipt,
        string $submissionKey,
    ): TransferDriverExpense {
        // Fast path for the ordinary retry: found before anything is written, so no duplicate photo is stored.
        if ($existing = self::findRetry($transfer, $by, $submissionKey)) {
            return $existing;
        }

        $path = $receipt->store('driver-expenses/'.$transfer->getKey(), TransferDriverExpense::DISK);

        if ($path === false) {
            throw new \RuntimeException('Could not store the receipt photo.');
        }

        $insert = fn (string $key) => self::insert($transfer, $by, $type, $amount, $receipt, $path, $key);
        $discardFile = fn () => Storage::disk(TransferDriverExpense::DISK)->delete($path);

        try {
            return $insert($submissionKey);
        } catch (QueryException $e) {
            if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                $discardFile();

                throw $e;
            }

            // Lost the race to an identical simultaneous request: hand back the winner's expense.
            if ($existing = self::findRetry($transfer, $by, $submissionKey)) {
                $discardFile();

                return $existing;
            }

            // The token is somebody ELSE's (a forged or reused request). It means nothing beyond
            // de-duplication, so this is a genuinely new expense: mint a fresh token and store it.
            try {
                return $insert((string) Str::uuid());
            } catch (Throwable $retry) {
                $discardFile();

                throw $retry;
            }
        } catch (Throwable $e) {
            // Whatever went wrong, this upload's file must not be left behind orphaned.
            $discardFile();

            throw $e;
        }
    }

    /**
     * Its own (save)point: a unique violation in the rare simultaneous-double-tap race must not poison an
     * enclosing transaction — Postgres refuses every further statement in one that failed.
     */
    private static function insert(
        Transfer $transfer,
        Driver $by,
        DriverExpenseType $type,
        int $amount,
        UploadedFile $receipt,
        string $path,
        string $submissionKey,
    ): TransferDriverExpense {
        return DB::transaction(fn () => TransferDriverExpense::create([
            'transfer_id' => $transfer->getKey(),
            'driver_id' => $by->getKey(),
            'type' => $type,
            'amount' => $amount,
            'currency' => TransferDriverExpense::CURRENCY,
            'receipt_path' => $path,
            // Detected from the file's CONTENT, not from what the browser claimed.
            'receipt_mime' => (string) $receipt->getMimeType(),
            'receipt_size' => (int) $receipt->getSize(),
            'submission_key' => $submissionKey,
            'status' => DriverExpenseStatus::New,
        ]));
    }

    /** Same person, same trip, same token: a retry of a submission that already went through. */
    private static function findRetry(Transfer $transfer, Driver $by, string $submissionKey): ?TransferDriverExpense
    {
        return TransferDriverExpense::query()
            ->where('submission_key', $submissionKey)
            ->where('transfer_id', $transfer->getKey())
            ->where('driver_id', $by->getKey())
            ->first();
    }
}
