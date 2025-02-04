<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 *
 * @property Collection<TrainTariff> $tariffs
 */
class Train extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function tariffs(): HasMany
    {
        return $this->hasMany(TrainTariff::class);
    }
}
