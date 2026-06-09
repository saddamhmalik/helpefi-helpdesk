<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Repositories\MemberRepository;
use App\Support\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportService
{
    public function __construct(private MemberRepository $members)
    {
    }

    public function csv(): StreamedResponse
    {
        return CsvStream::download(CsvStream::timestampedFilename('team-members'), function ($handle) {
            fputcsv($handle, [
                'Name',
                'Email',
                'Role',
                'Teams',
                'MFA enabled',
                'Created',
            ]);

            $this->members->exportEmployees(function ($member) use ($handle) {
                fputcsv($handle, [
                    $member->name,
                    $member->email,
                    $member->roles->pluck('name')->join(', '),
                    $member->teams->pluck('name')->join(', '),
                    $member->two_factor_confirmed_at ? 'yes' : 'no',
                    $member->created_at?->toDateTimeString(),
                ]);
            });
        });
    }
}
