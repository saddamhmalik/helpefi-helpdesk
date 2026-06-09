<?php

namespace App\Domains\Auth\Services;

use App\Domains\Contacts\Services\ContactService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PortalAuthService
{
    public function __construct(private ContactService $contacts)
    {
    }

    public function register(string $name, string $email, string $password): User
    {
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An account with this email already exists.',
            ]);
        }

        $contact = $this->contacts->findOrCreateByEmail($email, $name);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'contact_id' => $contact->id,
        ]);

        $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('customer'));

        return $user;
    }

    public function attemptLogin(string $email, string $password, bool $remember = false): User
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if (! $user->hasRole('customer')) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Please use the agent login for staff accounts.',
            ]);
        }

        request()->session()->regenerate();

        return $user;
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
