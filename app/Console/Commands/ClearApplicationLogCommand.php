<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearApplicationLogCommand extends Command
{
    protected $signature = 'logs:clear';

    protected $description = 'Clear the application log file';

    public function handle(): int
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            $this->info('No application log file to clear.');

            return self::SUCCESS;
        }

        file_put_contents($path, '');

        $this->info('Application log cleared.');

        return self::SUCCESS;
    }
}
