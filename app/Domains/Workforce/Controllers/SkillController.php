<?php

namespace App\Domains\Workforce\Controllers;

use App\Domains\Workforce\Services\SkillService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    public function __construct(private SkillService $skills)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Skills', [
            'skills' => $this->skills->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->skills->create($data['name']);

        return back()->with('success', 'Skill created.');
    }

    public function update(Request $request, int $skill): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->skills->update($skill, $data['name']);

        return back()->with('success', 'Skill updated.');
    }

    public function destroy(int $skill): RedirectResponse
    {
        $this->skills->delete($skill);

        return back()->with('success', 'Skill deleted.');
    }
}
