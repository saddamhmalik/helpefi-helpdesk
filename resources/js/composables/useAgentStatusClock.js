import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

export function useAgentStatusClock() {
    const page = usePage();
    const { locale } = useI18n();
    const now = ref(new Date());
    let timer = null;

    const timezone = computed(() => page.props.timezone ?? page.props.helpdesk?.timezone ?? 'UTC');

    const formatted = computed(() => now.value.toLocaleString(locale.value, {
        timeZone: timezone.value,
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }));

    const timezoneLabel = computed(() => {
        try {
            const parts = new Intl.DateTimeFormat(locale.value, {
                timeZone: timezone.value,
                timeZoneName: 'short',
            }).formatToParts(now.value);

            return parts.find((part) => part.type === 'timeZoneName')?.value ?? timezone.value;
        } catch {
            return timezone.value;
        }
    });

    const isoTimestamp = computed(() => now.value.toISOString());

    onMounted(() => {
        timer = window.setInterval(() => {
            now.value = new Date();
        }, 30000);
    });

    onUnmounted(() => {
        if (timer) {
            clearInterval(timer);
        }
    });

    return {
        formatted,
        timezoneLabel,
        isoTimestamp,
    };
}
