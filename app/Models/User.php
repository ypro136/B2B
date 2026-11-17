<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'language',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Enforce the two-accounts-per-company rule for company users.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if ($user->company_id === null) {
                return;
            }

            $existing = self::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->count();

            if ($existing >= 2) {
                throw ValidationException::withMessages([
                    'company_id' => 'A company can have at most two user accounts.',
                ]);
            }
        });
    }

    /**
     * The company this user belongs to (null for platform admins).
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Roles assigned to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Determine whether the user has the given role.
     */
    public function hasRole(string|Role $role): bool
    {
        $name = $role instanceof Role ? $role->name : $role;

        return $this->roles()->where('name', $name)->exists();
    }

    /**
     * Determine whether the user has any of the given roles.
     *
     * @param  list<string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Determine whether the user is a platform administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN]);
    }

    /**
     * Determine whether the user is a super administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * Feature permissions granted to a regular administrator.
     */
    public function adminPermissions(): HasMany
    {
        return $this->hasMany(AdminPermission::class, 'admin_user_id');
    }

    /**
     * Feature permissions granted by this administrator.
     */
    public function grantedPermissions(): HasMany
    {
        return $this->hasMany(AdminPermission::class, 'granted_by');
    }

    /**
     * Determine whether the user may use the given admin feature.
     */
    public function hasFeature(string $feature): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->adminPermissions()->where('feature', $feature)->exists();
    }

    /**
     * Bookings created by the user.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'booked_by');
    }

    /**
     * Seat requests submitted by the user.
     */
    public function seatRequests(): HasMany
    {
        return $this->hasMany(SeatRequest::class, 'requested_by');
    }

    /**
     * Seat requests reviewed by the user.
     */
    public function reviewedSeatRequests(): HasMany
    {
        return $this->hasMany(SeatRequest::class, 'reviewed_by');
    }

    /**
     * Trips created by the user.
     */
    public function tripsCreated(): HasMany
    {
        return $this->hasMany(Trip::class, 'created_by');
    }
}
