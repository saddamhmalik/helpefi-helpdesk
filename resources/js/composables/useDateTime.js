import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

export function useDateTime() {
    const page = usePage();
    const { locale } = useI18n();

    const timezone = computed(() => page.props.timezone ?? page.props.helpdesk?.timezone ?? 'UTC');

    const formatDateTime = (value, options = {}) => {
        if (!value) {
            return '—';
        }

        return new Date(value).toLocaleString(locale.value, {
            timeZone: timezone.value,
            ...options,
        });
    };

    const formatDate = (value, options = {}) => {
        if (!value) {
            return '—';
        }

        return new Date(value).toLocaleDateString(locale.value, {
            timeZone: timezone.value,
            ...options,
        });
    };

    const formatTime = (value, options = {}) => {
        if (!value) {
            return '—';
        }

        return new Date(value).toLocaleTimeString(locale.value, {
            timeZone: timezone.value,
            ...options,
        });
    };

    return {
        timezone,
        formatDateTime,
        formatDate,
        formatTime,
    };
}
