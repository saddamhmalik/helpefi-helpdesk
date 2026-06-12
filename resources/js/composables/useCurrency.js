import { computed, toValue } from 'vue';
import { usePage } from '@inertiajs/vue3';

const fallbackCurrency = { code: 'USD', symbol: '$', name: 'US Dollar' };

function resolveCurrency(source, page) {
    const resolved = source != null ? toValue(source) : null;

    if (resolved && typeof resolved === 'object' && resolved.symbol) {
        return resolved;
    }

    if (typeof resolved === 'string' && resolved.length === 3) {
        const fromPage = page.props.billing?.currency?.code === resolved
            ? page.props.billing.currency
            : page.props.currency?.code === resolved
                ? page.props.currency
                : null;

        if (fromPage) {
            return fromPage;
        }
    }

    return page.props.billing?.currency
        ?? page.props.currency
        ?? fallbackCurrency;
}

export function useCurrency(source = null) {
    const page = usePage();

    const currency = computed(() => resolveCurrency(source, page));

    const formatPrice = (amount, suffix = '') => {
        const meta = currency.value;

        if (amount === null || amount === undefined || amount === '') {
            return '';
        }

        try {
            const formatted = new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: meta.code,
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }).format(Number(amount));

            return suffix ? `${formatted}${suffix}` : formatted;
        } catch {
            const value = `${meta.symbol}${amount}`;

            return suffix ? `${value}${suffix}` : value;
        }
    };

    return { currency, formatPrice };
}
