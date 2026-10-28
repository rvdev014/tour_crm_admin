<?php

namespace App\Models;

use App\Enums\TrainWebClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $train_id
 * @property int $from_city_id
 * @property int $to_city_id
 * @property float|null $price_economy
 * @property float|null $price_business
 * @property float|null $price_vip
 *
 * @property-read Train $train
 * @property-read City $fromCity
 * @property-read City $toCity
 */
class TrainWebPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'train_id',
        'from_city_id',
        'to_city_id',
        'price_economy',
        'price_business',
        'price_vip',
    ];

    protected $casts = [
        'price_economy' => 'float',
        'price_business' => 'float',
        'price_vip' => 'float',
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

    public function priceFor(TrainWebClass $class): ?float
    {
        return $this->{$class->priceColumn()};
    }
}
