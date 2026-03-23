<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * UserAdmin Model
 * Represents admin and police users for AdminSide authentication
 * Separate from users_public table for UserSide app users
 */
class UserAdmin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_admin';

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
        'station_id',
        'is_verified',
        'email_verified_at',
        'verification_token',
        'token_expires_at',
        'reset_token',
        'reset_token_expires_at',
        'total_flags',
        'false_report_count',
        'spam_count',
        'harassment_count',
        'inappropriate_content_count',
        'last_flag_date',
        'restriction_level',
        'trust_score',
        'remember_token',
        'failed_login_attempts',
        'lockout_until',
        'last_failed_login',
        'user_role',
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
        'password' => 'hashed',
        'is_verified' => 'boolean',
        'latitude' => 'double',
        'longitude' => 'double',
        'email_verified_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'reset_token_expires_at' => 'datetime',
        'lockout_until' => 'datetime',
        'last_failed_login' => 'datetime',
        'last_flag_date' => 'datetime',
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

    /**
     * Get the guard for this authenticatable.
     *
     * @return string
     */
    public function getAuthPasswordName()
    {
        return 'password';
    }

    /**
     * Relationships
     */

    /**
     * Get RBAC roles assigned to this admin/police user
     */
    public function adminRoles()
    {
        return $this->belongsToMany(
            AdminRole::class,
            'user_admin_roles',
            'user_admin_id',
            'role_id'
        );
    }

    /**
     * Get all permissions through roles (RBAC)
     */
    public function permissions()
    {
        return $this->hasManyThrough(
            AdminPermission::class,
            AdminRole::class,
            'id',           // FK on admin_roles
            'id',           // FK on admin_permissions
            'id',           // Local key on user_admin
            'id'            // Local key on admin_roles (through pivot)
        );
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission($permissionName)
    {
        return $this->adminRoles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('permission_name', $permissionName);
            })
            ->exists();
    }

    /**
     * Check if user has a specific role (uses in-memory collection to avoid repeat DB queries)
     */
    public function hasRole($roleName)
    {
        // Use the already-loaded relation if available, otherwise load it once
        if (!$this->relationLoaded('adminRoles')) {
            $this->load('adminRoles');
        }
        return $this->adminRoles->contains('role_name', $roleName);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Check if user is station admin
     */
    public function isStationAdmin()
    {
        return $this->hasRole('station_admin');
    }

    /**
     * Check if user is police officer
     */
    public function isPoliceOfficer()
    {
        return $this->hasRole('police_officer');
    }

    /**
     * Get the police station this user belongs to
     */
    public function policeStation()
    {
        return $this->belongsTo(PoliceStation::class, 'station_id', 'station_id');
    }

    /**
     * Get officer profile if exists
     */
    public function officer()
    {
        return $this->hasOne(PoliceOfficer::class, 'user_id', 'id');
    }

    /**
     * Get admin actions performed by this user
     */
    public function adminActions()
    {
        return $this->hasMany(AdminAction::class, 'admin_id');
    }

    /**
     * Get audit logs for actions performed by this user
     */
    public function auditLogs()
    {
        return $this->hasMany(AdminAuditLog::class, 'user_admin_id');
    }

    /**
     * Get login attempts for this user
     */
    public function loginAttempts()
    {
        return $this->hasMany(AdminLoginAttempt::class, 'user_admin_id');
    }

    /**
     * Get successful login attempts
     */
    public function successfulLogins()
    {
        return $this->loginAttempts()->where('status', 'success');
    }

    /**
     * Get roles assigned by this user
     */
    public function assignedRoles()
    {
        return $this->hasMany(UserAdminRole::class, 'assigned_by');
    }
}
