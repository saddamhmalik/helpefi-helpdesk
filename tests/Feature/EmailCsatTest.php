<?php

namespace Tests\Feature;

use App\Domains\Channels\Mail\CsatSurveyMail;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Csat\Models\CsatResponse;
use App\Domains\Csat\Models\CsatSetting;
use App\Domains\Csat\Services\CsatSurveyMailer;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\CsatSeeder;
use Database\Seeders\EmailSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailCsatTest extends TestCase
{
    use RefreshDatabase;

    private function enableEmailCsat(): void
    {
        $this->seed([TicketLookupSeeder::class, CsatSeeder::class, EmailSeeder::class]);

        CsatSetting::query()->first()->update([
            'enabled' => true,
            'email_enabled' => true,
        ]);

        \App\Domains\Channels\Models\MailSetting::query()->first()->update(['enabled' => true]);
    }

    private function openTicketFor(Contact $contact): Ticket
    {
        $open = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        return Ticket::query()->create([
            'number' => 'HD-EMAIL01',
            'subject' => 'Email CSAT ticket',
            'contact_id' => $contact->id,
            'ticket_status_id' => $open->id,
            'ticket_priority_id' => $priority->id,
        ]);
    }

    public function test_csat_email_sent_when_ticket_is_closed(): void
    {
        Mail::fake();
        $this->enableEmailCsat();

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $ticket = $this->openTicketFor($contact);
        $closed = TicketStatus::query()->where('slug', 'closed')->first();

        $ticket->update(['ticket_status_id' => $closed->id]);

        Mail::assertSent(CsatSurveyMail::class, function (CsatSurveyMail $mail) use ($contact) {
            return $mail->hasTo($contact->email);
        });

        $this->assertNotNull($ticket->fresh()->csat_email_sent_at);
    }

    public function test_csat_email_is_not_sent_twice(): void
    {
        Mail::fake();
        $this->enableEmailCsat();

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $ticket = $this->openTicketFor($contact);
        $closed = TicketStatus::query()->where('slug', 'closed')->first();

        $ticket->update(['ticket_status_id' => $closed->id]);
        $ticket->update(['ticket_status_id' => $closed->id]);

        Mail::assertSent(CsatSurveyMail::class, 1);
    }

    public function test_signed_email_survey_can_be_submitted(): void
    {
        $this->seed([TicketLookupSeeder::class, CsatSeeder::class]);

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $closed = TicketStatus::query()->where('slug', 'closed')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $ticket = Ticket::query()->create([
            'number' => 'HD-EMAIL02',
            'subject' => 'Closed ticket',
            'contact_id' => $contact->id,
            'ticket_status_id' => $closed->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $submitUrl = app(CsatSurveyMailer::class)->signedSubmitUrl($ticket);

        $this->post($submitUrl, [
            'rating' => 4,
            'comment' => 'Good support',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('csat_responses', [
            'ticket_id' => $ticket->id,
            'rating' => 4,
            'channel' => CsatResponse::CHANNEL_EMAIL,
        ]);
    }

    public function test_quick_email_rate_submits_without_comment(): void
    {
        $this->seed([TicketLookupSeeder::class, CsatSeeder::class]);

        CsatSetting::query()->first()->update(['comment_required' => true]);

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $closed = TicketStatus::query()->where('slug', 'closed')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $ticket = Ticket::query()->create([
            'number' => 'HD-EMAIL03',
            'subject' => 'Closed ticket',
            'contact_id' => $contact->id,
            'ticket_status_id' => $closed->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $rateUrl = app(CsatSurveyMailer::class)->signedRateUrl($ticket, 5);

        $this->get($rateUrl)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('csat_responses', [
            'ticket_id' => $ticket->id,
            'rating' => 5,
            'channel' => CsatResponse::CHANNEL_EMAIL,
        ]);
    }

    public function test_csat_report_includes_channel_breakdown(): void
    {
        $this->seed([TicketLookupSeeder::class, CsatSeeder::class]);

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $closed = TicketStatus::query()->where('slug', 'closed')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $portalTicket = Ticket::query()->create([
            'number' => 'HD-PORT01',
            'subject' => 'Portal ticket',
            'contact_id' => $contact->id,
            'ticket_status_id' => $closed->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $emailTicket = Ticket::query()->create([
            'number' => 'HD-MAIL01',
            'subject' => 'Email ticket',
            'contact_id' => $contact->id,
            'ticket_status_id' => $closed->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $csat = app(\App\Domains\Csat\Services\CsatService::class);
        $csat->submit($portalTicket, $contact, 5, 'Portal');
        $csat->submit($emailTicket, $contact, 4, null, CsatResponse::CHANNEL_EMAIL);

        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/reports?type=csat&run=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('result.summary.by_channel.portal', 1)
                ->where('result.summary.by_channel.email', 1));
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->seed([TicketLookupSeeder::class, CsatSeeder::class]);

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $ticket = $this->openTicketFor($contact);

        URL::forceRootUrl('http://localhost');

        $this->get("/portal/csat/email/{$ticket->id}?contact={$contact->id}")
            ->assertForbidden();
    }
}
