<?php

namespace App\Console\Commands;

use App\Domains\Tenancy\Support\CentralDomain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseCentralCommand extends Command
{
    protected $signature = 'platform:diagnose-central {host? : Hostname to test, defaults to CENTRAL_APP_DOMAIN}';

    protected $description = 'Print central domain and database diagnostics';

    public function handle(): int
    {
        $host = (string) ($this->argument('host') ?: config('tenancy.central_app_domain'));
        $configCached = file_exists(base_path('bootstrap/cache/config.php'));

        $this->line('Config cached: '.($configCached ? 'yes' : 'no'));
        $this->line('APP_URL: '.config('app.url'));
        $this->line('CENTRAL_APP_DOMAIN: '.config('tenancy.central_app_domain'));
        $this->line('central_domains: '.implode(', ', config('tenancy.central_domains', [])));
        $this->line('Test host: '.$host);
        $this->line('isCentralHost: '.(CentralDomain::isCentralHost($host) ? 'yes' : 'no'));
        $this->newLine();

        $driver = (string) config('database.connections.central.driver');
        $database = (string) config('database.connections.central.database');

        $this->line('Central DB driver: '.$driver);
        $this->line('Central DB name: '.$database);
        $pdoDrivers = \PDO::getAvailableDrivers();
        $this->line('PDO drivers: '.($pdoDrivers !== [] ? implode(', ', $pdoDrivers) : '(none)'));

        if ($driver === 'sqlite') {
            $this->error('Central DB is set to sqlite. Tenants may still work via MySQL tenant DBs.');
            $this->line('Fix .env: DB_CONNECTION=central, DB_DRIVER=mysql, CENTRAL_DB_DRIVER=mysql, DB_DATABASE=helpdesk_central');
            $this->line('Then: php artisan config:clear && php artisan config:cache');
        }

        if ($driver === 'mysql' && ! in_array('mysql', $pdoDrivers, true)) {
            $this->error('Central DB driver is mysql but pdo_mysql is not loaded for CLI PHP.');
            $this->line('Install: sudo apt install php-mysql (match your PHP version)');
        }

        if (! CentralDomain::isCentralHost($host)) {
            $this->error("Host \"{$host}\" is not recognized as central.");
            $this->line('Set CENTRAL_APP_DOMAIN in .env to match your apex domain, then rebuild config cache.');
        }

        if ($driver === 'mysql' && in_array('mysql', $pdoDrivers, true)) {
            try {
                DB::connection('central')->getPdo();
                $tenants = DB::connection('central')->table('tenants')->count();
                $this->info("Central DB connection: OK ({$tenants} tenant(s))");
            } catch (\Throwable $e) {
                $this->error('Central DB connection failed: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
