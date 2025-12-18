<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Filament\Panel;
use App\Enums\Gender;
use App\Enums\UserRole;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $birthday
 * @property Gender $gender
 * @property string $avatar
 * @property string $email
 * @property string $password
 * @property int $operator_percent_tps
 * @property string $timezone
 * @property Carbon $timezone_updated_at
 * @property UserRole $role
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'operator_percent_tps',
        'avatar',
        'birthday',
        'gender',
        'phone',
        'google_id',
        'timezone',
        'timezone_updated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'timezone_updated_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'gender' => Gender::class
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role !== UserRole::User;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isAccountant(): bool
    {
        return $this->role === UserRole::Accountant;
    }

    public function isOperator(): bool
    {
        return $this->role === UserRole::Operator;
    }

    public function isSeniorOperator(): bool
    {
        return $this->role === UserRole::SeniorOperator;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        return Storage::disk('public')->url($this->avatar);
    }
}
