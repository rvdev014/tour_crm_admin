<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

enum TransportType: string
{
    case ONE = '1';
    case TWO = '2';
    case THREE = '3';
}

class TransportClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'description',
        'price_per_km',
        'photo',
    ];

    protected $casts = [
        'type' => TransportType::class,
        'price_per_km' => 'decimal:2',
    ];
}
