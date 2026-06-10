<?php

namespace App\Domains\ServiceDesk\Services;

use App\Domains\ServiceDesk\Models\ApprovalRequest;
use App\Domains\ServiceDesk\Models\ApprovalRequestStep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class ApprovalMailer
{
    public function signedReviewUrl(ApprovalRequest $request, ApprovalRequestStep $step, ?Carbon $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'approvals.email.review',
            $expiresAt ?? now()->addDays(7),
            [
                'approval' => $request->id,
                'step' => $step->id,
                'approver' => $step->approver_user_id,
            ],
        );
    }
}
