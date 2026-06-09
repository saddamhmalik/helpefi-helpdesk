<?php

namespace App\Domains\Integrations\Controllers;

use App\Domains\Integrations\Services\JiraIntegrationService;
use App\Domains\Integrations\Services\LinearIntegrationService;
use App\Domains\Integrations\Services\TicketExternalIssueService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundIntegrationController extends Controller
{
    public function __construct(
        private TicketExternalIssueService $issues,
        private JiraIntegrationService $jira,
        private LinearIntegrationService $linear,
    ) {
    }

    public function jira(Request $request): JsonResponse
    {
        if (! $this->jira->verifySecret($request->header('X-Integration-Secret'))) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $this->issues->handleJiraWebhook($request->all());

        return response()->json(['ok' => true]);
    }

    public function linear(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! $this->linear->verifySignature($payload, $request->header('Linear-Signature'))) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $this->issues->handleLinearWebhook($request->all());

        return response()->json(['ok' => true]);
    }
}
