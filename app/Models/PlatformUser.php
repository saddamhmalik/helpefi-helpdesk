<?php

namespace App\Models;

use App\Domains\Platform\Models\PlatformRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PlatformUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $connection = 'central';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            PlatformRole::class,
            'model',
            'platform_model_has_roles',
            'model_id',
            'role_id',
        );
    }

    public function syncRoles(array $roleNames): void
    {
        $roleIds = PlatformRole::query()
            ->whereIn('name', $roleNames)
            ->pluck('id')
            ->all();

        $this->roles()->sync($roleIds);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    public function permissionNames(): array
    {
        if ($this->hasRole('super_admin') || $this->isBootstrapAdmin()) {
            return ['*'];
        }

        return $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn (PlatformRole $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    public function isBootstrapAdmin(): bool
    {
        if ($this->roles()->exists()) {
            return false;
        }

        return static::query()->count() === 1;
    }
}
