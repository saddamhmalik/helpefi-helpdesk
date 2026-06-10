<?php

namespace App\Domains\Channels\Controllers\Api;

use App\Domains\Channels\Services\MessagingSettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessagingSettingController extends Controller
{
    public function __construct(private MessagingSettingService $messaging)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($this->messaging->snapshot());
    }

    public function update(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'account_sid' => ['nullable', 'string', 'max:255'],
            'auth_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_from' => ['nullable', 'string', 'max:50'],
            'sms_from' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json($this->messaging->update($data));
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
