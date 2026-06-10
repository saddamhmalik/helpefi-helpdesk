<?php

namespace App\Domains\Contacts\Controllers\Api;

use App\Domains\Contacts\Services\CustomerContextService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerContextController extends Controller
{
    public function __construct(private CustomerContextService $customerContext)
    {
    }

    public function show(int $ticket): JsonResponse
    {
        $context = $this->customerContext->forTicket($ticket);

        if (! $context) {
            return response()->json(['message' => 'No requester on this ticket.'], 404);
        }

        return response()->json($context);
    }

    public function refresh(Request $request, int $ticket): JsonResponse
    {
        $context = $this->customerContext->forTicket($ticket, refreshCrm: true);

        if (! $context) {
            return response()->json(['message' => 'No requester on this ticket.'], 404);
        }

        return response()->json($context);
    }
}
