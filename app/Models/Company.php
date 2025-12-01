<?php

namespace App\Models;

use App\Enums\CompanyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $inn
 * @property string $email
 * @property string $comment
 * @property CompanyType $type
 * @property int $additional_percent
 * @property int|null $group_id
 *
 * @property Group $group
 */
class Company extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'inn',
        'email',
        'type',
        'comment',
        'additional_percent',
        'group_id'
    ];

    protected $casts = [
        'type' => CompanyType::class,
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
