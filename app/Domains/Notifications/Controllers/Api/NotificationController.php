<?php

namespace App\Domains\Notifications\Controllers\Api;

use App\Domains\Notifications\Services\NotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->notifications->list($request->user()));
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->notifications->inboxSummary($request->user()));
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $this->notifications->markRead($request->user(), $notification);

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($request->user());

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function settings(): JsonResponse
    {
        return response()->json($this->notifications->settingsSnapshot());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'email_enabled' => ['required', 'boolean'],
            'notify_ticket_assigned' => ['required', 'boolean'],
            'notify_customer_reply' => ['required', 'boolean'],
            'notify_sla_breach' => ['required', 'boolean'],
        ]);

        return response()->json($this->notifications->updateSettings($data));
    }
}
