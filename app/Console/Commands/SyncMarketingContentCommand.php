<?php

namespace App\Console\Commands;

use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use Database\Seeders\MarketingBlogPostSeeder;
use Database\Seeders\PlatformEmailTemplateSeeder;
use Illuminate\Console\Command;

class SyncMarketingContentCommand extends Command
{
    protected $signature = 'marketing:sync-content';

    protected $description = 'Seed marketing blog posts, email templates, and refresh marketing cache';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => PlatformEmailTemplateSeeder::class,
            '--force' => true,
        ]);

        $this->call('db:seed', [
            '--class' => MarketingBlogPostSeeder::class,
            '--force' => true,
        ]);

        CentralMarketingPresenter::forgetCache();

        $this->info('Marketing content synced.');

        return self::SUCCESS;
    }
}
