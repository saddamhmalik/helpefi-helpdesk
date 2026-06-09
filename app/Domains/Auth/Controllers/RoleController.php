<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Services\RoleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private RoleService $roles)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Roles', [
            'roles' => $this->roles->list(),
            'catalog' => $this->roles->catalog(),
            'protectedRoles' => config('permissions.protected_roles', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        $this->roles->create($data['name'], $data['permissions'] ?? []);

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, int $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        $this->roles->update($role, $data['name'] ?? null, $data['permissions']);

        return back()->with('success', 'Role updated.');
    }

    public function destroy(int $role): RedirectResponse
    {
        $this->roles->delete($role);

        return back()->with('success', 'Role deleted.');
    }
}
