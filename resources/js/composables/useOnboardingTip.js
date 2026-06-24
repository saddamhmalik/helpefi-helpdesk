import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useOnboardingTip(key) {
    const page = usePage();
    const visible = ref(false);

    const storageKey = computed(() => {
        const tenantId = page.props.tenantId ?? 'tenant';
        const userId = page.props.auth?.user?.id ?? 'guest';

        return `onboarding-tip:${tenantId}:${userId}:${key}`;
    });

    onMounted(() => {
        visible.value = localStorage.getItem(storageKey.value) !== '1';
    });

    const dismiss = () => {
        visible.value = false;
        localStorage.setItem(storageKey.value, '1');
    };

    return {
        visible,
        dismiss,
    };
}
