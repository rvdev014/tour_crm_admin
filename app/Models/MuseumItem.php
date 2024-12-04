<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $name
 * @property string $description
 * @property int $museum_id
 * @property float $price_per_person
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Museum $museum
 */
class MuseumItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'museum_id',
        'price_per_person',
    ];

    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }
}
