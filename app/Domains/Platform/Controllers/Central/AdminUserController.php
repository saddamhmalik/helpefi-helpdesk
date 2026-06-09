<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformRoleService;
use App\Domains\Platform\Services\PlatformUserService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(
        private PlatformUserService $users,
        private PlatformRoleService $roles,
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Central/Admin/Users/Index', [
            'users' => $this->users->list((int) $request->integer('per_page', 20)),
            'roles' => $this->roles->assignableRoles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Central/Admin/Users/Form', [
            'roles' => $this->roles->assignableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->users->validationRules(creating: true));

        $this->users->create($data);

        return redirect()->route('central.admin.users.index')->with('success', 'User created.');
    }

    public function edit(int $user): Response
    {
        $record = $this->users->show($user);

        return Inertia::render('Central/Admin/Users/Form', [
            'user' => [
                'id' => $record->id,
                'name' => $record->name,
                'email' => $record->email,
                'is_active' => $record->is_active,
                'roles' => $record->roles->pluck('name')->all(),
            ],
            'roles' => $this->roles->assignableRoles(),
        ]);
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $data = $request->validate($this->users->validationRules(creating: false));

        $this->users->update($user, $data);

        return redirect()->route('central.admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, int $user): RedirectResponse
    {
        $this->users->delete($user, $request->user('platform'));

        return back()->with('success', 'User deleted.');
    }
}
