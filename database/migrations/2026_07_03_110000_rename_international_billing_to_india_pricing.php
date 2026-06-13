<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KEY_MAP = [
        'price_monthly_usd' => 'price_monthly_india',
        'price_yearly_usd' => 'price_yearly_india',
        'razorpay_plan_id_monthly_usd' => 'razorpay_plan_id_monthly_india',
        'razorpay_plan_id_yearly_usd' => 'razorpay_plan_id_yearly_india',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('central_settings')) {
            return;
        }

        if (Schema::hasColumn('central_settings', 'international_billing') && ! Schema::hasColumn('central_settings', 'india_pricing')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->renameColumn('international_billing', 'india_pricing');
            });
        }

        $this->remapCatalogKeys(self::KEY_MAP);
    }

    public function down(): void
    {
        if (! Schema::hasTable('central_settings')) {
            return;
        }

        if (Schema::hasColumn('central_settings', 'india_pricing') && ! Schema::hasColumn('central_settings', 'international_billing')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->renameColumn('india_pricing', 'international_billing');
            });
        }

        $this->remapCatalogKeys(array_flip(self::KEY_MAP));
    }

    private function remapCatalogKeys(array $map): void
    {
        DB::table('central_settings')
            ->whereNotNull('plan_catalog')
            ->orderBy('id')
            ->each(function (object $row) use ($map) {
                $catalog = json_decode((string) $row->plan_catalog, true);

                if (! is_array($catalog)) {
                    return;
                }

                $changed = false;

                foreach ($catalog as $slug => $plan) {
                    if (! is_array($plan)) {
                        continue;
                    }

                    foreach ($map as $from => $to) {
                        if (array_key_exists($from, $plan)) {
                            $plan[$to] = $plan[$from];
                            unset($plan[$from]);
                            $changed = true;
                        }
                    }

                    $catalog[$slug] = $plan;
                }

                if ($changed) {
                    DB::table('central_settings')
                        ->where('id', $row->id)
                        ->update(['plan_catalog' => json_encode($catalog)]);
                }
            });
    }
};
