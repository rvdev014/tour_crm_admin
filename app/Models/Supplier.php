<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $inn
 * @property string $company_name
 */
class Supplier extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
}
