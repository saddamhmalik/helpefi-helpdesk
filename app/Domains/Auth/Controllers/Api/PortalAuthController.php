<?php

namespace App\Domains\Auth\Controllers\Api;

use App\Domains\Auth\Services\ApiTokenService;
use App\Domains\Auth\Services\PortalAuthService;
use App\Domains\Knowledge\Services\PortalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalAuthController extends Controller
{
    public function __construct(
        private PortalAuthService $portalAuth,
        private ApiTokenService $tokens,
        private PortalService $portalService,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->portalAuth->register($data['name'], $data['email'], $data['password']);
        $token = $this->tokens->createToken($user);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->portalAuth->attemptLogin($data['email'], $data['password']);
        $token = $this->tokens->createToken($user);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function myTickets(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('customer')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($this->portalService->ticketsForUser($request->user()));
    }

    public function myTicket(Request $request, int $ticket): JsonResponse
    {
        if (! $request->user()->hasRole('customer')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($this->portalService->customerTicket($request->user(), $ticket));
    }
}
