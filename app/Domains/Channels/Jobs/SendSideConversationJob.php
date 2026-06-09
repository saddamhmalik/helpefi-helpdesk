<?php

namespace App\Domains\Channels\Jobs;

use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\SideConversations\Repositories\SideConversationRepository;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSideConversationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $sideConversationId,
        public int $messageId,
        public int $agentId,
    ) {
        $this->afterCommit();
    }

    public function handle(OutboundMailService $mail, SideConversationRepository $conversations): void
    {
        $conversation = $conversations->find($this->sideConversationId);
        $message = $conversation->messages()->findOrFail($this->messageId);
        $agent = User::query()->findOrFail($this->agentId);

        $mail->deliverSideConversation($conversation, $message, $agent);
    }
}
