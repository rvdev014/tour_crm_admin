<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transport_type
 * @property int $transport_comfort_level
 * @property float $price
 * @property int $company_id
 * @property int $status
 * @property int $pax
 * @property string $comment
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Company $company
 */
class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transport_type',
        'transport_comfort_level',
        'price',
        'company_id',
        'status',
        'pax',
        'comment',
    ];

    protected $casts = [
        'status' => ExpenseStatus::class,
        'transport_type' => TransportType::class,
        'transport_comfort_level' => TransportComfortLevel::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
