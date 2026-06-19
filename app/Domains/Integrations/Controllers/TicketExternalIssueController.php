<?php

namespace App\Domains\Integrations\Controllers;

use App\Domains\Integrations\Services\TicketExternalIssueService;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TicketExternalIssueController extends Controller
{
    public function __construct(private TicketExternalIssueService $issues)
    {
    }

    public function store(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:jira,linear'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            if (! empty($data['reference'])) {
                $this->issues->linkIssue($ticket, $data['provider'], $data['reference']);
            } else {
                $this->issues->createIssue($ticket, $data['provider'], $request->user()->id);
            }
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $this->issues->integrationErrorMessage($data['provider'], $exception));
        } catch (RequestException $exception) {
            return back()->with('error', $this->issues->integrationApiErrorMessage($data['provider'], $exception));
        } catch (AuthorizationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'External issue linked.');
    }

    public function destroy(int $ticket, int $issue): RedirectResponse
    {
        $this->issues->unlinkIssue($ticket, $issue);

        return back()->with('success', 'External issue unlinked.');
    }

    public function sync(int $ticket): RedirectResponse
    {
        $this->issues->refreshForTicket($ticket);

        return back();
    }
}
