<?php

namespace App\Models;

use App\Enums\CompanyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $inn
 * @property string $comment
 * @property CompanyType $type
 * @property int $additional_percent
 */
class Company extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'inn',
        'type',
        'comment',
        'additional_percent'
    ];

    protected $casts = [
        'type' => CompanyType::class,
    ];
}
