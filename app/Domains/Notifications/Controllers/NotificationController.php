<?php

namespace App\Domains\Notifications\Controllers;

use App\Domains\Notifications\Services\NotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'unread' => $request->boolean('unread'),
            'type' => $request->string('type')->toString() ?: null,
        ];

        return Inertia::render('Notifications/Index', [
            'notifications' => $this->notifications->list($request->user(), filters: $filters),
            'filters' => $filters,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->notifications->inboxSummary($request->user()));
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $this->notifications->markRead($request->user(), $notification);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return back()->with('success', 'All notifications marked as read.');
    }

    public function clearRead(Request $request): RedirectResponse
    {
        $this->notifications->clearRead($request->user());

        return back()->with('success', 'Read notifications cleared.');
    }
}
