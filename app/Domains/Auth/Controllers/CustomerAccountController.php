<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Services\MemberService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerAccountController extends Controller
{
    public function __construct(private MemberService $memberService)
    {
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('contacts.index', ['access' => 'portal']);
    }

    public function destroy(Request $request, int $customer): RedirectResponse
    {
        $this->memberService->removeCustomer($customer, $request->user());

        return back()->with('success', 'Customer account removed.');
    }
}
