<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $chat_id
 */
class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'chat_id',
    ];
}
