<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Models\PlatformTestimonial;
use App\Domains\Platform\Services\PlatformTestimonialService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTestimonialController extends Controller
{
    public function __construct(private PlatformTestimonialService $testimonials)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Central/Admin/Testimonials/Index', [
            'testimonials' => $this->testimonials->listForAdmin(),
            'testimonialsEnabled' => $this->testimonials->marketingEnabled(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Central/Admin/Testimonials/Form', [
            'testimonial' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->testimonials->validationRules());
        $this->testimonials->create($data);

        return redirect()->route('central.admin.testimonials.index')->with('success', 'Testimonial saved.');
    }

    public function edit(int $testimonial): Response
    {
        return Inertia::render('Central/Admin/Testimonials/Form', [
            'testimonial' => $this->testimonials->findForAdmin($testimonial),
        ]);
    }

    public function update(Request $request, int $testimonial): RedirectResponse
    {
        $existing = PlatformTestimonial::query()->findOrFail($testimonial);
        $data = $request->validate($this->testimonials->validationRules($existing));
        $this->testimonials->update($testimonial, $data);

        return redirect()->route('central.admin.testimonials.index')->with('success', 'Testimonial updated.');
    }

    public function destroy(int $testimonial): RedirectResponse
    {
        $this->testimonials->delete($testimonial);

        return redirect()->route('central.admin.testimonials.index')->with('success', 'Testimonial deleted.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'testimonials_enabled' => ['required', 'boolean'],
        ]);

        $this->testimonials->setMarketingEnabled((bool) $data['testimonials_enabled']);

        return back()->with('success', 'Testimonial visibility updated.');
    }
}
