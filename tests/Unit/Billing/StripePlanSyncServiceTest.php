<?php

namespace Tests\Unit\Billing;

use App\Domains\Billing\Repositories\StripeCatalogRepository;
use App\Domains\Billing\Services\StripePlanSyncService;
use Mockery;
use Tests\TestCase;

class StripePlanSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_sync_catalog_is_skipped_when_stripe_is_disabled(): void
    {
        $repository = Mockery::mock(StripeCatalogRepository::class);
        $repository->shouldReceive('isEnabled')->once()->andReturn(false);
        $repository->shouldNotReceive('createProduct');

        $service = new StripePlanSyncService($repository);

        $catalog = [
            'starter' => [
                'slug' => 'starter',
                'name' => 'Starter',
                'price' => 29,
                'limits' => [],
                'features' => [],
            ],
        ];

        $this->assertSame($catalog, $service->syncCatalog($catalog, 'USD'));
    }

    public function test_sync_catalog_creates_product_and_price_when_missing(): void
    {
        $repository = Mockery::mock(StripeCatalogRepository::class);
        $repository->shouldReceive('isEnabled')->once()->andReturn(true);
        $repository->shouldReceive('retrieveProduct')->never();
        $repository->shouldReceive('retrievePrice')->never();
        $repository->shouldReceive('createProduct')
            ->once()
            ->with('Starter', 'starter')
            ->andReturn((object) ['id' => 'prod_new']);
        $repository->shouldReceive('updateProduct')
            ->once()
            ->with('prod_new', 'Starter', 'starter')
            ->andReturn((object) ['id' => 'prod_new']);
        $repository->shouldReceive('createRecurringPrice')
            ->once()
            ->with('prod_new', 2900, 'USD', 'starter')
            ->andReturn((object) ['id' => 'price_new']);

        $service = new StripePlanSyncService($repository);

        $result = $service->syncCatalog([
            'starter' => [
                'slug' => 'starter',
                'name' => 'Starter',
                'price' => 29,
                'limits' => [],
                'features' => [],
            ],
        ], 'USD');

        $this->assertSame('prod_new', $result['starter']['stripe_product_id']);
        $this->assertSame('price_new', $result['starter']['stripe_price_id']);
    }

    public function test_sync_catalog_reuses_matching_price(): void
    {
        $repository = Mockery::mock(StripeCatalogRepository::class);
        $repository->shouldReceive('isEnabled')->once()->andReturn(true);
        $repository->shouldReceive('retrieveProduct')->once()->with('prod_existing')->andReturn((object) ['id' => 'prod_existing']);
        $repository->shouldReceive('updateProduct')
            ->once()
            ->with('prod_existing', 'Starter', 'starter')
            ->andReturn((object) ['id' => 'prod_existing']);
        $repository->shouldReceive('retrievePrice')
            ->once()
            ->with('price_existing')
            ->andReturn((object) [
                'id' => 'price_existing',
                'active' => true,
                'product' => 'prod_existing',
                'unit_amount' => 2900,
                'currency' => 'usd',
                'recurring' => (object) ['interval' => 'month'],
            ]);
        $repository->shouldNotReceive('createRecurringPrice');
        $repository->shouldNotReceive('archivePrice');

        $service = new StripePlanSyncService($repository);

        $result = $service->syncCatalog([
            'starter' => [
                'slug' => 'starter',
                'name' => 'Starter',
                'price' => 29,
                'stripe_product_id' => 'prod_existing',
                'stripe_price_id' => 'price_existing',
                'limits' => [],
                'features' => [],
            ],
        ], 'USD');

        $this->assertSame('price_existing', $result['starter']['stripe_price_id']);
    }

    public function test_sync_catalog_creates_new_price_when_amount_changes(): void
    {
        $repository = Mockery::mock(StripeCatalogRepository::class);
        $repository->shouldReceive('isEnabled')->once()->andReturn(true);
        $repository->shouldReceive('retrieveProduct')->once()->with('prod_existing')->andReturn((object) ['id' => 'prod_existing']);
        $repository->shouldReceive('updateProduct')
            ->once()
            ->with('prod_existing', 'Starter', 'starter')
            ->andReturn((object) ['id' => 'prod_existing']);
        $repository->shouldReceive('retrievePrice')
            ->once()
            ->with('price_existing')
            ->andReturn((object) [
                'id' => 'price_existing',
                'active' => true,
                'product' => 'prod_existing',
                'unit_amount' => 2900,
                'currency' => 'usd',
                'recurring' => (object) ['interval' => 'month'],
            ]);
        $repository->shouldReceive('archivePrice')->once()->with('price_existing');
        $repository->shouldReceive('createRecurringPrice')
            ->once()
            ->with('prod_existing', 3500, 'USD', 'starter')
            ->andReturn((object) ['id' => 'price_updated']);

        $service = new StripePlanSyncService($repository);

        $result = $service->syncCatalog([
            'starter' => [
                'slug' => 'starter',
                'name' => 'Starter',
                'price' => 35,
                'stripe_product_id' => 'prod_existing',
                'stripe_price_id' => 'price_existing',
                'limits' => [],
                'features' => [],
            ],
        ], 'USD');

        $this->assertSame('price_updated', $result['starter']['stripe_price_id']);
    }
}
