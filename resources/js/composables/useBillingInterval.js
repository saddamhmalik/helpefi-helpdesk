import { computed, ref } from 'vue';

export function useBillingInterval() {
    const billingInterval = ref('month');

    const intervalSuffix = computed(() => (billingInterval.value === 'year' ? '/yr' : '/mo'));

    const planPrice = (plan, india = false) => {
        if (india) {
            return billingInterval.value === 'year'
                ? (plan.price_yearly_india ?? 0)
                : (plan.price_monthly_india ?? 0);
        }

        if (billingInterval.value === 'year') {
            return plan.price_yearly ?? (plan.price_monthly ?? plan.price) * 10;
        }

        return plan.price_monthly ?? plan.price;
    };

    const yearlySavingsPercent = (plan, india = false) => {
        const monthly = india
            ? (plan.price_monthly_india ?? 0)
            : (plan.price_monthly ?? plan.price ?? 0);
        const yearly = india
            ? (plan.price_yearly_india ?? monthly * 10)
            : (plan.price_yearly ?? monthly * 10);

        if (monthly <= 0) {
            return 0;
        }

        const annualAtMonthly = monthly * 12;

        return Math.max(0, Math.round((1 - yearly / annualAtMonthly) * 100));
    };

    const billingReadyForInterval = (plan) => {
        if (billingInterval.value === 'year') {
            return plan.billing_ready_yearly ?? false;
        }

        return plan.billing_ready_monthly ?? plan.billing_ready ?? false;
    };

    return {
        billingInterval,
        intervalSuffix,
        planPrice,
        yearlySavingsPercent,
        billingReadyForInterval,
    };
}
