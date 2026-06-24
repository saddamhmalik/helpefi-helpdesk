<?php

namespace App\Models;

use App\Domains\Auth\Services\PasswordResetMailService;
use App\Domains\Contacts\Models\Contact;
use App\Support\AvatarSupport;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'locale', 'timezone', 'appearance', 'avatar_type', 'avatar_path', 'avatar_disk', 'password', 'contact_id', 'custom_fields', 'sso_subject', 'sso_provider'])]
#[Hidden(['password', 'remember_token', 'api_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $appends = ['avatar_url'];

    public function personalAccessTokens(): HasMany
    {
        return $this->hasMany(PersonalAccessToken::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Workforce\Models\Team::class, 'team_user')
            ->withPivot('org_role')
            ->withTimestamps();
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Workforce\Models\Skill::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        app(PasswordResetMailService::class)->send($this, $token);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return AvatarSupport::url($this);
    }
}
