import { computed, ref } from 'vue';

export function useBillingInterval() {
    const billingInterval = ref('month');

    const intervalSuffix = computed(() => (billingInterval.value === 'year' ? '/yr' : '/mo'));

    const planPrice = (plan) => {
        if (billingInterval.value === 'year') {
            return plan.price_yearly ?? (plan.price_monthly ?? plan.price) * 10;
        }

        return plan.price_monthly ?? plan.price;
    };

    const yearlySavingsPercent = (plan) => {
        const monthly = plan.price_monthly ?? plan.price ?? 0;
        const yearly = plan.price_yearly ?? monthly * 10;

        if (monthly <= 0) {
            return 0;
        }

        const annualAtMonthly = monthly * 12;

        return Math.max(0, Math.round((1 - yearly / annualAtMonthly) * 100));
    };

    const stripeReadyForInterval = (plan) => {
        if (billingInterval.value === 'year') {
            return plan.stripe_ready_yearly ?? false;
        }

        return plan.stripe_ready_monthly ?? plan.stripe_ready ?? false;
    };

    return {
        billingInterval,
        intervalSuffix,
        planPrice,
        yearlySavingsPercent,
        stripeReadyForInterval,
    };
}
