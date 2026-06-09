<?php

namespace App\Domains\SideConversations\Controllers;

use App\Domains\SideConversations\Services\SideConversationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SideConversationController extends Controller
{
    public function __construct(
        private SideConversationService $sideConversations,
    ) {
    }

    public function store(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $this->sideConversations->create(
            $ticket,
            $request->user()->id,
            $data['recipient_email'],
            $data['recipient_name'] ?? null,
            $data['subject'],
            $data['body'],
        );

        return back()->with('success', 'Side conversation started.');
    }

    public function reply(Request $request, int $ticket, int $sideConversation): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $this->sideConversations->reply(
            $ticket,
            $sideConversation,
            $request->user()->id,
            $data['body'],
        );

        return back()->with('success', 'Side conversation reply sent.');
    }

    public function close(Request $request, int $ticket, int $sideConversation): RedirectResponse
    {
        $this->sideConversations->close($ticket, $sideConversation, $request->user()->id);

        return back()->with('success', 'Side conversation closed.');
    }
}
