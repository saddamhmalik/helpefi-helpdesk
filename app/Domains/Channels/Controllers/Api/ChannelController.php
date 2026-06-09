<?php

namespace App\Domains\Channels\Controllers\Api;

use App\Domains\Channels\Services\ChannelService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ChannelController extends Controller
{
    public function __construct(private ChannelService $channelService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->channelService->all());
    }

    public function inboundEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_email' => ['required', 'email'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'message_id' => ['nullable', 'string', 'max:255'],
            'in_reply_to' => ['nullable', 'array'],
            'in_reply_to.*' => ['string', 'max:255'],
            'references' => ['nullable', 'array'],
            'references.*' => ['string', 'max:255'],
            'ticket_number' => ['nullable', 'string', 'max:32'],
            'to_email' => ['nullable', 'email'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.filename' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.content' => ['required_with:attachments', 'string'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:127'],
            'cc_emails' => ['nullable', 'array'],
            'cc_emails.*' => ['email', 'max:255'],
        ]);

        try {
            $result = $this->channelService->processInboundEmail(
                $data,
                $request->header('X-Channel-Token'),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result, $result['action'] === 'created' ? 201 : 200);
    }
}
