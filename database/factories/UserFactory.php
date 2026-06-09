<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->roles()->count() === 0) {
                $user->assignRole(Role::findOrCreate('agent'));
            }
        });
    }

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function customer(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->syncRoles([]);
            $user->assignRole(Role::findOrCreate('customer'));
        });
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->syncRoles([]);
            $user->assignRole(Role::findOrCreate('admin'));
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
