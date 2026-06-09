import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const fallbackCurrency = { code: 'USD', symbol: '$', name: 'US Dollar' };

export function useCurrency(source = null) {
    const page = usePage();

    const currency = computed(() => {
        if (source?.value) {
            return source.value;
        }

        return page.props.billing?.currency
            ?? page.props.currency
            ?? fallbackCurrency;
    });

    const formatPrice = (amount, suffix = '') => {
        const value = `${currency.value.symbol}${amount}`;

        return suffix ? `${value}${suffix}` : value;
    };

    return { currency, formatPrice };
}
