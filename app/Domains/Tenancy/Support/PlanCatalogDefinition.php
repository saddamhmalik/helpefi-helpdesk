<?php

namespace App\Domains\Tenancy\Support;

class PlanCatalogDefinition
{
    public static function defaultPlans(): array
    {
        return config('plans', []);
    }

    public static function slugs(): array
    {
        return array_keys(self::defaultPlans());
    }

    public static function limitDefinitions(): array
    {
        return config('plan_catalog.limits', []);
    }

    public static function featureDefinitions(): array
    {
        return config('plan_catalog.features', []);
    }

    public static function featureKeys(): array
    {
        return array_keys(self::featureDefinitions());
    }

    public static function limitKeys(): array
    {
        return array_keys(self::limitDefinitions());
    }

    public static function defaultYearlyPrice(int $monthly): int
    {
        return $monthly > 0 ? $monthly * 10 : 0;
    }

    public static function priceForInterval(array $plan, string $interval = 'month'): int
    {
        if ($interval === 'year') {
            return (int) ($plan['price_yearly'] ?? self::defaultYearlyPrice((int) ($plan['price_monthly'] ?? $plan['price'] ?? 0)));
        }

        return (int) ($plan['price_monthly'] ?? $plan['price'] ?? 0);
    }

    public static function razorpayPlanIdForInterval(array $plan, string $interval = 'month'): ?string
    {
        if ($interval === 'year') {
            $yearly = $plan['razorpay_plan_id_yearly'] ?? null;

            return $yearly !== null && $yearly !== '' ? (string) $yearly : null;
        }

        $monthly = $plan['razorpay_plan_id_monthly'] ?? $plan['razorpay_plan_id'] ?? null;

        return $monthly !== null && $monthly !== '' ? (string) $monthly : null;
    }

    public static function defaultCatalog(): array
    {
        $catalog = [];

        foreach (self::defaultPlans() as $slug => $plan) {
            $catalog[$slug] = self::normalizePlan($slug, $plan);
        }

        return $catalog;
    }

    public static function normalizePlan(string $slug, array $plan): array
    {
        $limits = [];

        foreach (self::limitKeys() as $key) {
            $value = $plan['limits'][$key] ?? self::defaultPlans()[$slug]['limits'][$key] ?? null;
            $limits[$key] = self::normalizeLimit($value);
        }

        $features = collect($plan['features'] ?? [])
            ->filter(fn ($feature) => in_array($feature, self::featureKeys(), true))
            ->values()
            ->all();

        $priceMonthly = max(0, (int) ($plan['price_monthly'] ?? $plan['price'] ?? 0));
        $priceYearly = array_key_exists('price_yearly', $plan) && $plan['price_yearly'] !== ''
            ? max(0, (int) $plan['price_yearly'])
            : self::defaultYearlyPrice($priceMonthly);
        $razorpayPlanIdMonthly = self::resolveRazorpayPlanIdMonthly($slug, $plan);
        $razorpayPlanIdYearly = self::resolveRazorpayPlanIdYearly($slug, $plan);

        return [
            'slug' => $slug,
            'name' => (string) ($plan['name'] ?? ucfirst($slug)),
            'price' => $priceMonthly,
            'price_monthly' => $priceMonthly,
            'price_yearly' => $priceYearly,
            'razorpay_plan_id' => $razorpayPlanIdMonthly,
            'razorpay_plan_id_monthly' => $razorpayPlanIdMonthly,
            'razorpay_plan_id_yearly' => $razorpayPlanIdYearly,
            'limits' => $limits,
            'features' => $features,
        ];
    }

    public static function mergeCatalog(?array $stored): array
    {
        $catalog = self::defaultCatalog();

        if ($stored === null || $stored === []) {
            return $catalog;
        }

        foreach ($catalog as $slug => $defaults) {
            if (! isset($stored[$slug])) {
                continue;
            }

            $merged = array_merge($defaults, $stored[$slug]);

            if (isset($stored[$slug]['features'])) {
                $merged['features'] = array_values(array_unique(array_merge(
                    $defaults['features'] ?? [],
                    $stored[$slug]['features'] ?? [],
                )));
            }

            $catalog[$slug] = self::normalizePlan($slug, $merged);
        }

        return $catalog;
    }

    public static function catalogFromLegacyPricing(?array $pricing): array
    {
        $catalog = self::defaultCatalog();

        if ($pricing === null || $pricing === []) {
            return $catalog;
        }

        foreach ($pricing as $slug => $override) {
            if (! isset($catalog[$slug])) {
                continue;
            }

            $catalog[$slug] = self::normalizePlan($slug, array_merge($catalog[$slug], $override));
        }

        return $catalog;
    }

    public static function forAdminUi(): array
    {
        return [
            'limits' => collect(self::limitDefinitions())
                ->map(fn (array $definition, string $key) => [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? '',
                    'allow_unlimited' => (bool) ($definition['allow_unlimited'] ?? false),
                    'min' => (int) ($definition['min'] ?? 1),
                    'max' => (int) ($definition['max'] ?? 999999),
                ])
                ->values()
                ->all(),
            'features' => collect(self::featureDefinitions())
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                ])
                ->values()
                ->all(),
        ];
    }

    private static function normalizeLimit(mixed $value): ?int
    {
        if ($value === null || $value === 'unlimited' || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }

    private static function resolveRazorpayPlanIdMonthly(string $slug, array $plan): ?string
    {
        if (isset($plan['razorpay_plan_id_monthly']) && $plan['razorpay_plan_id_monthly'] !== '') {
            return (string) $plan['razorpay_plan_id_monthly'];
        }

        if (isset($plan['razorpay_plan_id']) && $plan['razorpay_plan_id'] !== '') {
            return (string) $plan['razorpay_plan_id'];
        }

        $fromEnv = config('billing.razorpay_plans.'.$slug);

        return $fromEnv ? (string) $fromEnv : null;
    }

    private static function resolveRazorpayPlanIdYearly(string $slug, array $plan): ?string
    {
        if (isset($plan['razorpay_plan_id_yearly']) && $plan['razorpay_plan_id_yearly'] !== '') {
            return (string) $plan['razorpay_plan_id_yearly'];
        }

        $fromEnv = config('billing.razorpay_plans_yearly.'.$slug);

        return $fromEnv ? (string) $fromEnv : null;
    }
}
