<?php

namespace App\Models;

use App\Enums\TourStatus;
use App\Enums\WebTourStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $comment
 * @property int|null $user_id
 * @property TourStatus $status
 */
class ContactRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => WebTourStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
