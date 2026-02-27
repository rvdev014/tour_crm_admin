<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use App\Services\ExpenseService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $web_tour_id
 * @property int $pax_count
 * @property float $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read WebTour $webTour
 */
class WebTourFreePrice extends Model
{
    use HasFactory;
    
    protected $appends = [
        'price_usd',
        'price_uzs',
    ];
    
    /**
     * Атрибуты, для которых разрешено массовое заполнение.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'web_tour_id',
        'pax_count',
        'price',
    ];
    
    /**
     * Атрибуты, которые должны быть приведены к базовым типам.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'pax_count' => 'integer',
        'price' => 'decimal:2', // Соответствует настройке decimal в миграции
    ];
    
    /**
     * Получить тур, к которому относится эта цена.
     */
    public function webTour(): BelongsTo
    {
        return $this->belongsTo(WebTour::class, 'web_tour_id');
    }
    
    public function getPriceUzsAttribute(): float
    {
        $currencyUsd = ExpenseService::getUsdToUzsCurrency();
        return round($this->price * ($currencyUsd?->rate ?? 1), 2);
    }
    
    public function getPriceUsdAttribute(): float
    {
        return round($this->price, 2);
    }
}