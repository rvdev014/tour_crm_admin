<?php

namespace App\Models;

use App\Enums\DriverExpenseStatus;
use App\Enums\DriverExpenseType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Money a driver spent on a trip (parking, fuel, ...), with a photo of the receipt.
 *
 * A record for operators to review — it is deliberately NOT wired into Transfer/Tour price or profit
 * calculations.
 *
 * @property int $id
 * @property int $transfer_id
 * @property int|null $driver_id who entered it (the driver, or a dispatcher on their behalf)
 * @property DriverExpenseType $type
 * @property string $amount
 * @property string $currency
 * @property string $receipt_path path on the PRIVATE disk
 * @property string $receipt_mime
 * @property int $receipt_size
 * @property string|null $submission_key
 * @property DriverExpenseStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon $created_at
 * @property Transfer $transfer
 * @property Driver|null $addedBy
 * @property User|null $reviewedBy
 */
class TransferDriverExpense extends Model
{
    use HasFactory;

    /** Receipts live here, never on the public disk. */
    public const DISK = 'local';

    public const CURRENCY = 'UZS';

    protected $guarded = ['id'];

    protected $casts = [
        'type' => DriverExpenseType::class,
        'status' => DriverExpenseStatus::class,
        'amount' => 'decimal:2',
        'receipt_size' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // A deleted expense must not leave its receipt photo behind on disk.
        static::deleted(function (TransferDriverExpense $expense) {
            Storage::disk(self::DISK)->delete($expense->receipt_path);
        });
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Whoever entered an expense can take it back — but only while nobody has reviewed it, and never
     * somebody else's. There is no editing: delete and re-enter, so a reviewed amount cannot be changed.
     */
    public function canBeDeletedBy(Driver $viewer): bool
    {
        return $this->status === DriverExpenseStatus::New
            && $this->driver_id !== null
            && $this->driver_id === $viewer->getKey();
    }

    /** "150 000 UZS" */
    public function formattedAmount(): string
    {
        return number_format((float) $this->amount, 0, '.', ' ').' '.$this->currency;
    }
}
