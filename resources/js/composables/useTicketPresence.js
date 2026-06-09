import { ref, watch, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { getSharedRealtimeClient } from '../lib/realtimeClient.js';
import { ticketChannel } from '../lib/realtimeChannels.js';

export function useTicketPresence(ticketIdRef, composingRef) {
    const page = usePage();
    const viewers = ref([]);
    let heartbeatTimer = null;
    let subscribedTicketId = null;
    let presenceHandler = null;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

    const jsonHeaders = () => ({
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
    });

    const sendHeartbeat = async () => {
        const ticketId = ticketIdRef.value;

        if (!ticketId) {
            return;
        }

        await fetch(`/workspace/tickets/${ticketId}/presence`, {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ composing: composingRef?.value ?? false }),
        });
    };

    const leave = async () => {
        const ticketId = ticketIdRef.value;

        if (!ticketId) {
            return;
        }

        await fetch(`/workspace/tickets/${ticketId}/presence`, {
            method: 'DELETE',
            headers: jsonHeaders(),
        });
    };

    const unsubscribePresence = () => {
        const client = getSharedRealtimeClient(page.props.realtime);

        if (client && subscribedTicketId && presenceHandler) {
            client.unsubscribe(ticketChannel(page.props.tenantId, subscribedTicketId), presenceHandler);
        }

        subscribedTicketId = null;
        presenceHandler = null;
    };

    const subscribePresence = (ticketId) => {
        unsubscribePresence();

        const client = getSharedRealtimeClient(page.props.realtime);

        if (!client || !ticketId) {
            return;
        }

        const channel = ticketChannel(page.props.tenantId, ticketId);
        presenceHandler = (payload) => {
            if (payload.event === 'presence.updated') {
                viewers.value = payload.data?.viewers ?? [];
            }
        };

        client.subscribe(channel, presenceHandler);
        subscribedTicketId = ticketId;
    };

    const stop = () => {
        clearInterval(heartbeatTimer);
        heartbeatTimer = null;
        unsubscribePresence();
    };

    const start = () => {
        stop();
        viewers.value = [];
        sendHeartbeat();

        const ticketId = ticketIdRef.value;

        if (ticketId) {
            subscribePresence(ticketId);
        }

        heartbeatTimer = setInterval(sendHeartbeat, 15000);
    };

    watch(ticketIdRef, (id, previous) => {
        if (previous && previous !== id) {
            leave();
        }

        if (id) {
            start();
        } else {
            stop();
            viewers.value = [];
        }
    }, { immediate: true });

    if (composingRef) {
        watch(composingRef, () => {
            sendHeartbeat();
        });
    }

    onUnmounted(() => {
        stop();
        leave();
    });

    return { viewers };
}
