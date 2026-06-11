<?php

namespace App\Support;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class InertiaAuthRedirect
{
    public static function to(Request $request, string $url): Response
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->to($url);
    }
}
