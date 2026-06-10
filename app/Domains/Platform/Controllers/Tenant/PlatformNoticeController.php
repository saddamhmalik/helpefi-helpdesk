<?php

namespace App\Domains\Platform\Controllers\Tenant;

use App\Domains\Platform\Services\TenantPlatformNoticeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformNoticeController extends Controller
{
    public function __construct(private TenantPlatformNoticeService $notices)
    {
    }

    public function dismiss(Request $request, int $notice): RedirectResponse
    {
        $this->notices->dismiss($request->user(), $notice);

        return back();
    }
}
