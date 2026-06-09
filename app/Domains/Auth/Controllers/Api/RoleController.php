<?php

namespace App\Domains\Auth\Controllers\Api;

use App\Domains\Auth\Services\RoleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private RoleService $roles)
    {
    }

    public function index(): JsonResponse
    {
        abort_unless(request()->user()?->hasRole('admin'), 403);

        return response()->json([
            'roles' => $this->roles->list(),
            'catalog' => $this->roles->catalog(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        return response()->json(
            $this->roles->create($data['name'], $data['permissions'] ?? []),
            201,
        );
    }

    public function update(Request $request, int $role): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        return response()->json(
            $this->roles->update($role, $data['name'] ?? null, $data['permissions']),
        );
    }

    public function destroy(int $role): JsonResponse
    {
        abort_unless(request()->user()?->hasRole('admin'), 403);

        $this->roles->delete($role);

        return response()->json(['deleted' => true]);
    }
}
