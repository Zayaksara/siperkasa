<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
 * @property string|null $phone
 * @property string|null $portal_role
 * @property string|null $faculty
 * @property string|null $study_program_code
 * @property string|null $study_program
 * @property string|null $degree_level
 * @property int|null $entry_year
 * @property Carbon|null $portal_synced_at
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property UserRole $role
 * @property bool $is_active
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int $active_tasks
 */
#[Fillable([
    'nim_nip', 'name', 'email', 'password', 'role', 'is_active',
    'phone', 'portal_role', 'faculty', 'study_program_code', 'study_program',
    'degree_level', 'entry_year', 'portal_synced_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

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
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'portal_synced_at' => 'datetime',
            'entry_year' => 'integer',
        ];
    }

    /**
     * True once the account has logged in through the campus portal (Bridge).
     */
    public function isPortalAccount(): bool
    {
        return $this->portal_synced_at !== null;
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->nim_nip === config('siperkasa.super_admin_nip');
    }

    /**
     * Release categories that use this user as default PIC when the user is no longer an active PIC.
     */
    public function clearDefaultPicIfNotPic(): void
    {
        if (! $this->is_active || $this->role !== UserRole::Pic) {
            $this->defaultCategories()->update(['default_pic_id' => null]);
        }
    }

    /**
     * Categories whose default PIC is this user.
     */
    public function defaultCategories(): HasMany
    {
        return $this->hasMany(Category::class, 'default_pic_id');
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
