<?php

namespace App\Domains\Contacts\Services;

use App\Domains\Contacts\Repositories\OrganizationRepository;
use App\Support\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationExportService
{
    public function __construct(private OrganizationRepository $organizations)
    {
    }

    public function csv(): StreamedResponse
    {
        return CsvStream::download(CsvStream::timestampedFilename('organizations'), function ($handle) {
            fputcsv($handle, [
                'Name',
                'Website',
                'Phone',
                'Customer tier',
                'Domains',
                'Contacts',
                'Created',
            ]);

            $this->organizations->exportRows(function ($organization) use ($handle) {
                fputcsv($handle, [
                    $organization->name,
                    $organization->website,
                    $organization->phone,
                    $organization->customer_tier,
                    $organization->domains->pluck('domain')->join(', '),
                    $organization->contacts_count,
                    $organization->created_at?->toDateTimeString(),
                ]);
            });
        });
    }
}
