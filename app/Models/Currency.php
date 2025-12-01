<?php

namespace App\Models;

use App\Enums\CurrencyEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CurrencyEnum $from
 * @property CurrencyEnum $to
 * @property float $rate
// * @property float $inverse_rate
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Currency extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'from' => CurrencyEnum::class,
        'to' => CurrencyEnum::class,
    ];
}
