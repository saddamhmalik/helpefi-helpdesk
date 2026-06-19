<?php

namespace App\Console\Commands;

use App\Domains\Platform\Services\HelpefiLicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateHelpefiLicenseCommand extends Command
{
    protected $signature = 'helpefi:generate-license
        {organization : Licensed organization name}
        {--expires= : Expiry date (YYYY-MM-DD), defaults to one year from today}
        {--edition=self_hosted : License edition}';

    protected $description = 'Generate a self-hosted HELPEFI license key';

    public function handle(HelpefiLicenseService $licenses): int
    {
        $expiresAt = $this->option('expires')
            ? Carbon::parse((string) $this->option('expires'))
            : now()->addYear();

        $token = $licenses->generate(
            (string) $this->argument('organization'),
            $expiresAt,
            (string) $this->option('edition'),
        );

        $this->line('HELPEFI_LICENSE_KEY='.$token);
        $this->newLine();
        $this->comment('Store this value in the self-hosted .env file. Do not commit it to version control.');

        return self::SUCCESS;
    }
}
