<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Central/Home');
    }
}
