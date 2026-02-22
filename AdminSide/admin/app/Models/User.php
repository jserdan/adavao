<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users_public';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'firstname',
        'lastname', 
        'contact',
        'email',
        'password',
        'address',
        'latitude',
        'longitude',
        'is_verified',
        'station_id',
        'role',
        'email_verified_at',
        'verification_token',
        'token_expires_at',
        'reset_token',
        'reset_token_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'is_verified' => 'boolean',
        'latitude' => 'double',
        'longitude' => 'double',
        'email_verified_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'reset_token_expires_at' => 'datetime',
    ];
    
    /**
     * Auto-decrypt the contact field when accessed.
     */
    public function getContactAttribute($value)
    {
        if (empty($value) || !is_string($value)) {
            return $value;
        }
        return \App\Services\EncryptionService::decrypt($value);
    }

    /**
     * Auto-decrypt the address field when accessed.
     */
    public function getAddressAttribute($value)
    {
        if (empty($value) || !is_string($value)) {
            return $value;
        }
        return \App\Services\EncryptionService::decrypt($value);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
    
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
    
    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }
    
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
    }
    
    public function verifications()
    {
        return $this->hasMany(Verification::class, 'user_id');
    }
    
    public function verification()
    {
        return $this->hasOne(Verification::class, 'user_id')->latest();
    }
    
    public function adminActions()
    {
        return $this->hasMany(AdminAction::class, 'admin_id');
    }
    
    public function policeStation()
    {
        return $this->belongsTo(PoliceStation::class, 'station_id', 'station_id');
    }

    public function officer()
    {
        return $this->hasOne(PoliceOfficer::class, 'user_id', 'id');
    }

    public function patrolDispatches()
    {
        return $this->hasMany(PatrolDispatch::class, 'patrol_officer_id', 'id');
    }

    /**
     * Check if user has a specific role
     * Uses the Roles RBAC table if populated, falls back to legacy role column
     */
    public function hasRole($role)
    {
        // 1. Check the RBAC relationship (Over-engineered path)
        // detailed check against loaded roles relation to avoid N+1 if eager loaded
        foreach ($this->roles as $userRole) {
            if ($userRole->role_name === $role) {
                return true;
            }
        }
        
        // 2. Fallback to direct database query if relation not loaded
        if ($this->roles()->where('role_name', $role)->exists()) {
            return true;
        }

        // 3. Fallback to legacy 'role' column
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles
     * @param array $roles
     */
    public function hasAnyRole(array $roles)
    {
        // Check RBAC tables
        if ($this->roles()->whereIn('role_name', $roles)->exists()) {
            return true;
        }
        
        // Fallback to legacy
        return in_array($this->role, $roles);
    }
}