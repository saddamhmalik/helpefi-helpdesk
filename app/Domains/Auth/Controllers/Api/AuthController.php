<?php

namespace App\Domains\Auth\Controllers\Api;

use App\Domains\Auth\Services\ApiTokenService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private ApiTokenService $tokens)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'totp_code' => ['nullable', 'string'],
        ]);

        $user = $this->tokens->attemptLogin(
            $credentials['email'],
            $credentials['password'],
            $credentials['totp_code'] ?? null,
        );

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

    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revokeToken($request->user());

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
        ]);
    }
}
