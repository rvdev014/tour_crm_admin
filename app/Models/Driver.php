<?php

namespace App\Models;

use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Also the authenticatable for the driver cabinet (guard "driver"). Deliberately NOT a Filament
 * user and NOT a Foundation\Auth\User: there is no email, password reset or verification flow —
 * an operator sets the password in the CRM.
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $phone_normalized
 * @property string $chat_id
 * @property string|null $car_number
 * @property string|null $car_model
 * @property string|null $password
 * @property bool $is_active
 * @property string|null $locale
 * @property Carbon|null $last_login_at
 */
class Driver extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'chat_id',
        'car_number',
        'car_model',
        'password',
        'is_active',
        'locale',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * A driver with no password set has no cabinet access, but must fail like any wrong password.
     *
     * Hash::check() THROWS ("This password does not use the Bcrypt algorithm") when given a NULL or
     * empty hash instead of returning false, which would turn a login attempt on such an account into
     * a 500 and reveal which accounts have no password. Comparing against a throwaway hash of a random
     * string can never match, and costs the same time as a real check.
     */
    public function getAuthPassword(): string
    {
        return $this->password ?: Hash::make(Str::random(40));
    }

    protected static function booted(): void
    {
        // phone_normalized is the cabinet login key, so it must be right no matter who writes `phone`.
        static::saving(function (Driver $driver) {
            $driver->phone_normalized = PhoneNormalizer::uz($driver->phone);
        });
    }
}
