<?php

namespace App\Domains\Channels\Controllers;

use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Services\ChannelService;
use App\Domains\Chat\Services\ChatAvailabilityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function __construct(
        private ChannelService $channelService,
        private ChatAvailabilityService $chatAvailability,
    ) {
    }

    public function index(): Response
    {
        $chatChannel = $this->channelService->chatChannel();

        return Inertia::render('Settings/Channels', [
            'channels' => $this->channelService->all(),
            'appUrl' => config('app.url'),
            'chatAvailability' => $chatChannel && $chatChannel->type === Channel::TYPE_CHAT
                ? $this->chatAvailability->status($chatChannel)
                : null,
        ]);
    }

    public function update(Request $request, int $channel): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'settings.address' => ['nullable', 'email'],
            'settings.inbound_token' => ['nullable', 'string', 'max:255'],
            'settings.greeting' => ['nullable', 'string', 'max:500'],
            'settings.offline_message' => ['nullable', 'string', 'max:1000'],
            'settings.offline_mode' => ['nullable', 'string', 'in:never,business_hours,always'],
            'settings.allowed_origins' => ['nullable', 'array'],
            'settings.allowed_origins.*' => ['string', 'max:255'],
        ]);

        $this->channelService->update($channel, $data);

        return back()->with('success', 'Channel updated.');
    }
}
