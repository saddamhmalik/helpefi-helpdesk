import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    getSharedRealtimeClient,
    isRealtimeConfigured,
    onRealtimeConnectionChange,
} from '../lib/realtimeClient.js';

export function useRealtimeConnection() {
    const page = usePage();
    const connected = ref(false);
    const configured = computed(() => isRealtimeConfigured(page.props.realtime));
    let unsubscribe = null;

    const bind = () => {
        unsubscribe?.();
        unsubscribe = null;
        connected.value = false;

        if (!configured.value) {
            return;
        }

        getSharedRealtimeClient(page.props.realtime);
        unsubscribe = onRealtimeConnectionChange((state) => {
            connected.value = state;
        });
    };

    onMounted(bind);

    watch(
        () => page.props.realtime,
        bind,
        { deep: true },
    );

    onUnmounted(() => {
        unsubscribe?.();
    });

    return {
        connected,
        configured,
    };
}
