<?php

namespace App\Domains\Channels\Controllers\Api;

use App\Domains\Channels\Services\EmailInboxService;
use App\Domains\Channels\Services\OutboundMailService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EmailSettingController extends Controller
{
    public function __construct(
        private EmailInboxService $inboxes,
        private OutboundMailService $mail,
    ) {
    }

    public function inboxes(): JsonResponse
    {
        return response()->json($this->inboxes->listForSettings());
    }

    public function storeInbox(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'email', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $this->inboxes->assertUniqueAddress($data['address']);

        return response()->json($this->inboxes->create($data), 201);
    }

    public function updateInbox(Request $request, int $inbox): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'email', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $this->inboxes->assertUniqueAddress($data['address'], $inbox);

        return response()->json($this->inboxes->update($inbox, $data));
    }

    public function destroyInbox(Request $request, int $inbox): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $this->inboxes->delete($inbox);

        return response()->json(['deleted' => true]);
    }

    public function outboundSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        return response()->json($this->mail->settingsSnapshot());
    }

    public function updateOutbound(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'reply_enabled' => ['required', 'boolean'],
            'use_inbox_smtp' => ['required', 'boolean'],
            'email_inbox_id' => ['nullable', 'integer', 'exists:email_inboxes,id'],
            'driver' => ['required', 'in:smtp,log'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'in:tls,ssl,null'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        if (($data['encryption'] ?? null) === 'null') {
            $data['encryption'] = null;
        }

        return response()->json($this->mail->updateSettings($data));
    }

    public function testOutbound(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'to' => ['required', 'email'],
        ]);

        try {
            $this->mail->sendTest($data['to']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['sent' => true]);
    }
}
