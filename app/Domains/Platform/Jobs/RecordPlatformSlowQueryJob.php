<?php

namespace App\Domains\Platform\Jobs;

use App\Domains\Platform\Concerns\RunsOnCentralQueue;
use App\Domains\Platform\Repositories\PlatformSlowQueryRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordPlatformSlowQueryJob implements ShouldQueue
{
    use Queueable;
    use RunsOnCentralQueue;

    public int $tries = 3;

    public function __construct(
        public ?string $tenantId,
        public string $databaseConnection,
        public string $sql,
        public int $timeMs,
        public ?array $bindings,
        public ?string $method,
        public ?string $url,
        public ?string $routeName,
        public ?string $sourceFile,
        public ?int $sourceLine,
        public ?string $sourceCallable,
        public ?string $databaseHost,
        public ?string $databaseName,
    ) {
        $this->bindToCentralQueue();
    }

    public function handle(PlatformSlowQueryRepository $queries): void
    {
        $this->ensureCentralContext();

        $queries->create([
            'tenant_id' => $this->tenantId,
            'connection' => $this->databaseConnection,
            'database_host' => $this->databaseHost,
            'database_name' => $this->databaseName,
            'sql' => $this->sql,
            'time_ms' => $this->timeMs,
            'bindings' => $this->bindings,
            'method' => $this->method,
            'url' => $this->url,
            'route_name' => $this->routeName,
            'source_file' => $this->sourceFile,
            'source_line' => $this->sourceLine,
            'source_callable' => $this->sourceCallable,
        ]);
    }
}
