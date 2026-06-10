<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformNoticeService;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformNoticeImageController extends Controller
{
    public function __construct(private PlatformNoticeService $notices)
    {
    }

    public function __invoke(int $notice): StreamedResponse
    {
        return $this->notices->imageResponse($notice);
    }
}
