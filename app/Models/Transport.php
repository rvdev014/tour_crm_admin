<?php

namespace App\Models;

use App\Enums\EmployeeType;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property TransportType $type
 * @property TransportComfortLevel $comfort_level
 * @property float $price
 */
class Transport extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => TransportType::class,
        'comfort_level' => TransportComfortLevel::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
