<?php

namespace App\Domains\Contacts\Services;

use App\Domains\Contacts\Repositories\ContactRepository;
use App\Support\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportService
{
    public function __construct(private ContactRepository $contacts)
    {
    }

    public function csv(?string $search = null, ?string $access = null): StreamedResponse
    {
        return CsvStream::download(CsvStream::timestampedFilename('customers'), function ($handle) use ($search, $access) {
            fputcsv($handle, [
                'Name',
                'Email',
                'Phone',
                'Organization',
                'Portal access',
                'Tags',
                'Tickets',
                'Created',
            ]);

            $this->contacts->exportRows($search, $access, function ($contact) use ($handle) {
                fputcsv($handle, [
                    $contact->name,
                    $contact->email,
                    $contact->phone,
                    $contact->organization?->name,
                    $contact->portalUser ? 'yes' : 'no',
                    $contact->tags->pluck('name')->join(', '),
                    $contact->tickets_count,
                    $contact->created_at?->toDateTimeString(),
                ]);
            });
        });
    }
}
