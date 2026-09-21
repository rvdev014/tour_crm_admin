<?php

namespace App\Models;

use App\Enums\DriverTransferStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per driver-status change of a transfer. Append-only history; see
 * DriverTransferStatusService for why this is a table and not a JSON column.
 *
 * @property int $id
 * @property int $transfer_id
 * @property int|null $driver_id
 * @property DriverTransferStatus|null $from_status
 * @property DriverTransferStatus $to_status
 * @property string $source driver|dispatcher|admin|system (string(16): fits 'dispatcher', no migration needed)
 * @property Carbon $created_at
 * @property Transfer $transfer
 * @property Driver|null $driver
 */
class TransferDriverStatusLog extends Model
{
    use HasFactory;

    public const SOURCE_DRIVER = 'driver';

    public const SOURCE_DISPATCHER = 'dispatcher';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_SYSTEM = 'system';

    protected $guarded = ['id'];

    protected $casts = [
        'from_status' => DriverTransferStatus::class,
        'to_status' => DriverTransferStatus::class,
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
