<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function groupItems(): HasMany
    {
        return $this->hasMany(GroupItem::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function getPercent($price): float
    {
        /** @var GroupItem $groupItem */
        $groupItem = $this->groupItems()
            ->where('from_price', '<=', $price)
            ->where('to_price', '>=', $price)
            ->first();

        return $groupItem?->percent ?? 0;
    }
}
