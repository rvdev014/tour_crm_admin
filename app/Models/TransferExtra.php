<?php

namespace App\Models;

use App\Traits\HasLocaleFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An optional add-on a customer can attach to a transfer booking (baby
 * seat, child seat, …). Admin-managed via Filament so staff can add or
 * reprice extras without a deploy.
 *
 * @property int $id
 * @property string $name_ru
 * @property string|null $name_en
 * @property string $name
 * @property string|null $description_ru
 * @property string|null $description_en
 * @property string|null $description
 * @property float $price
 * @property int $max_quantity
 * @property bool $is_active
 * @property int $order
 */
class TransferExtra extends Model
{
    use HasFactory;
    use HasLocaleFields;

    protected $fillable = [
        'name_ru',
        'name_en',
        'description_ru',
        'description_en',
        'price',
        'max_quantity',
        'is_active',
        'order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_quantity' => 'integer',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function getNameAttribute(): ?string
    {
        return $this->getLocaleValue('name', default: $this->getRawOriginal('name_ru'));
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->getLocaleValue('description');
    }
}
