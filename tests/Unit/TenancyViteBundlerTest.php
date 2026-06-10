<?php

namespace Tests\Unit;

use Illuminate\Foundation\Vite;
use Stancl\Tenancy\Vite as TenancyVite;
use Tests\TestCase;

class TenancyViteBundlerTest extends TestCase
{
    public function test_vite_bundler_feature_is_enabled(): void
    {
        $this->assertContains(
            \Stancl\Tenancy\Features\ViteBundler::class,
            config('tenancy.features'),
        );
    }

    public function test_vite_resolves_to_tenancy_vite_class(): void
    {
        app(\Stancl\Tenancy\Tenancy::class);

        $this->assertInstanceOf(TenancyVite::class, app(Vite::class));
    }
}
