<?php

namespace App\Domains\Platform\Models;

use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class PlatformRole extends Model
{
    protected $connection = 'central';

    protected $table = 'platform_roles';

    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformPermission::class,
            'platform_role_has_permissions',
            'role_id',
            'permission_id',
        );
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            PlatformUser::class,
            'model',
            'platform_model_has_roles',
            'role_id',
            'model_id',
        );
    }
}
