<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string|null $nim_nip
 * @property string $name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string $role
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nim_nip', 'name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Aset yang di-PIC-i oleh user ini secara default.
     */
    public function picAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'default_pic_id');
    }

    /**
     * Laporan yang dibuat oleh user ini.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'user_id');
    }

    /**
     * Laporan yang divalidasi oleh user ini (sebagai admin).
     */
    public function adminReports(): HasMany
    {
        return $this->hasMany(Report::class, 'admin_id');
    }

    /**
     * Laporan yang ditugaskan kepada user ini (sebagai PIC).
     */
    public function assignedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'assigned_pic_id');
    }
}

