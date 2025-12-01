<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property float $from_price
 * @property float $to_price
 * @property float $percent
 *
 * @property Group $group
 */
class GroupItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'from_price',
        'to_price',
        'percent',
    ];

    protected $casts = [
        'from_price' => 'decimal:2',
        'to_price' => 'decimal:2',
        'percent' => 'decimal:2',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
