<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $train_id
 * @property int $from_city_id
 * @property int $to_city_id
 * @property float $class_business
 * @property float $class_vip
 * @property float $class_second
 *
 * @property Train $train
 */
class TrainTariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'train_id',
        'from_city_id',
        'to_city_id',
        'class_business',
        'class_vip',
        'class_second',
    ];

    public function train(): BelongsTo
    {
        return $this->belongsTo(Train::class);
    }

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'from_city_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'to_city_id');
    }
}
