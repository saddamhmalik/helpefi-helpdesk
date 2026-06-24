<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Mail\MarketingContactInquiryMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MarketingContactTest extends TestCase
{
    use RefreshDatabase;

    private function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_app_domain').$path;
    }

    private function withValidContactSession(): self
    {
        return $this->withSession([
            'marketing_contact_form_started_at' => now()->subSeconds(5)->timestamp,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Admin',
            'email' => 'jane@acme.test',
            'company' => 'Acme Inc',
            'topic' => 'sales',
            'message' => 'We would like a demo of Helpefi for our support team.',
            'website' => '',
        ], $overrides);
    }

    public function test_contact_page_renders_interactive_form(): void
    {
        config(['marketing_seo.organization.contact_email' => 'hello@helpefi.com']);

        $this->get($this->centralUrl('/contact'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Contact')
                ->where('contactEmail', 'hello@helpefi.com')
                ->where('turnstileSiteKey', null)
            );
    }

    public function test_contact_form_sends_inquiry_email(): void
    {
        Mail::fake();

        config(['marketing_seo.organization.contact_email' => 'hello@helpefi.com']);

        $this->withValidContactSession()
            ->post($this->centralUrl('/contact'), $this->validPayload())
            ->assertRedirect($this->centralUrl('/contact'));

        Mail::assertSent(MarketingContactInquiryMail::class, function (MarketingContactInquiryMail $mail) {
            return $mail->hasReplyTo('jane@acme.test', 'Jane Admin');
        });

        $this->assertDatabaseHas('marketing_leads', [
            'email' => 'jane@acme.test',
            'source' => 'contact',
            'intent' => 'sales',
        ], 'central');
    }

    public function test_contact_form_silently_discards_honeypot_submissions(): void
    {
        Mail::fake();

        $this->withValidContactSession()
            ->post($this->centralUrl('/contact'), $this->validPayload([
                'website' => 'https://spam.test',
            ]))
            ->assertRedirect($this->centralUrl('/contact'))
            ->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_contact_form_silently_discards_too_fast_submissions(): void
    {
        Mail::fake();

        $this->withSession([
            'marketing_contact_form_started_at' => now()->timestamp,
        ])->post($this->centralUrl('/contact'), $this->validPayload())
            ->assertRedirect($this->centralUrl('/contact'))
            ->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_contact_form_validates_required_fields(): void
    {
        $this->withValidContactSession()
            ->post($this->centralUrl('/contact'), [])
            ->assertSessionHasErrors(['name', 'email', 'topic', 'message']);
    }

    public function test_contact_form_rejects_invalid_topic(): void
    {
        $this->withValidContactSession()
            ->post($this->centralUrl('/contact'), $this->validPayload([
                'topic' => 'sql-injection',
            ]))
            ->assertSessionHasErrors('topic');
    }

    public function test_contact_form_is_rate_limited_by_ip(): void
    {
        Mail::fake();

        config([
            'marketing_seo.organization.contact_email' => 'hello@helpefi.com',
            'marketing_seo.contact_form.ip_max_attempts' => 2,
            'marketing_seo.contact_form.ip_decay_minutes' => 1,
            'marketing_seo.contact_form.email_max_attempts' => 100,
        ]);

        $payload = $this->validPayload();

        $this->withValidContactSession()->post($this->centralUrl('/contact'), $payload)->assertRedirect();
        $this->withValidContactSession()->post($this->centralUrl('/contact'), $payload)->assertRedirect();

        $this->withValidContactSession()->post($this->centralUrl('/contact'), $payload)
            ->assertSessionHasErrors('rate_limit');

        Mail::assertSentCount(2);
    }

    public function test_contact_form_is_rate_limited_by_email(): void
    {
        Mail::fake();

        config([
            'marketing_seo.organization.contact_email' => 'hello@helpefi.com',
            'marketing_seo.contact_form.ip_max_attempts' => 100,
            'marketing_seo.contact_form.email_max_attempts' => 2,
            'marketing_seo.contact_form.email_decay_minutes' => 60,
        ]);

        $payload = $this->validPayload(['email' => 'repeat@acme.test']);

        $this->withValidContactSession()->post($this->centralUrl('/contact'), $payload)->assertRedirect();
        $this->withValidContactSession()->post($this->centralUrl('/contact'), $payload)->assertRedirect();

        $this->withValidContactSession()->post($this->centralUrl('/contact'), $payload)
            ->assertSessionHasErrors('rate_limit');

        Mail::assertSentCount(2);
    }

    public function test_contact_form_requires_turnstile_when_enabled(): void
    {
        Mail::fake();

        config([
            'marketing_seo.organization.contact_email' => 'hello@helpefi.com',
            'marketing_seo.turnstile.secret_key' => 'test-secret',
            'marketing_seo.turnstile.site_key' => 'test-site',
        ]);

        $this->withValidContactSession()
            ->post($this->centralUrl('/contact'), $this->validPayload())
            ->assertSessionHasErrors('cf_turnstile_response');

        Mail::assertNothingSent();
    }

    public function test_contact_form_accepts_turnstile_when_enabled(): void
    {
        Mail::fake();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        config([
            'marketing_seo.organization.contact_email' => 'hello@helpefi.com',
            'marketing_seo.turnstile.secret_key' => 'test-secret',
            'marketing_seo.turnstile.site_key' => 'test-site',
        ]);

        $this->withValidContactSession()
            ->post($this->centralUrl('/contact'), $this->validPayload([
                'cf_turnstile_response' => 'valid-token',
            ]))
            ->assertRedirect($this->centralUrl('/contact'));

        Mail::assertSent(MarketingContactInquiryMail::class);
    }
}
