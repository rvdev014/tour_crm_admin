<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
