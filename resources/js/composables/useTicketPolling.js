import { onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { csrfHeaders } from '../support/csrf.js';
import { isRealtimeConfigured, onRealtimeConnectionChange } from '../lib/realtimeClient.js';

export function useTicketPolling(ticketIdRef, messagesRef, options = {}) {
    const page = usePage();
    const lastPollAt = ref(new Date().toISOString());
    const pulse = ref(null);
    let pollTimer = null;
    let pollWhenConnected = true;
    let unsubscribeConnection = null;

    const jsonHeaders = () => ({
        Accept: 'application/json',
        ...csrfHeaders(),
    });

    const resetPollCursor = () => {
        lastPollAt.value = new Date().toISOString();
        pulse.value = null;
    };

    const appendMessages = (newMessages) => {
        if (!newMessages?.length) {
            return;
        }

        const existing = new Set(messagesRef.value.map((item) => item.id));
        const merged = [...messagesRef.value];

        newMessages.forEach((message) => {
            if (!existing.has(message.id)) {
                merged.push(message);
                existing.add(message.id);
            }
        });

        if (merged.length !== messagesRef.value.length) {
            messagesRef.value = merged;
        }
    };

    const poll = async () => {
        const ticketId = ticketIdRef.value;

        if (!ticketId || !pollWhenConnected) {
            return;
        }

        const params = new URLSearchParams();

        if (lastPollAt.value) {
            params.set('since', lastPollAt.value);
        }

        if (pulse.value) {
            params.set('pulse', String(pulse.value));
        }

        try {
            const response = await fetch(`/workspace/tickets/${ticketId}/poll?${params.toString()}`, {
                headers: jsonHeaders(),
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            appendMessages(data.new_messages ?? []);

            if (data.server_time) {
                lastPollAt.value = data.server_time;
            }

            if (data.pulse) {
                pulse.value = data.pulse;
            }

            if (options.viewersRef && data.viewers) {
                options.viewersRef.value = data.viewers;
            }

            if (options.onUnreadCount && typeof data.unread_count === 'number') {
                options.onUnreadCount(data.unread_count);
            }

            if (data.ticket_changed && data.ticket && options.onTicketUpdate) {
                options.onTicketUpdate(data.ticket);
            }
        } catch {
        }
    };

    const start = () => {
        stop();
        poll();
        pollTimer = setInterval(poll, options.intervalMs ?? 4000);
    };

    const stop = () => {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    const syncPolling = () => {
        if (pollWhenConnected) {
            start();
        } else {
            stop();
        }
    };

    if (isRealtimeConfigured(page.props.realtime)) {
        pollWhenConnected = false;
        unsubscribeConnection = onRealtimeConnectionChange((connected) => {
            pollWhenConnected = !connected;
            syncPolling();
        });
    }

    watch(ticketIdRef, (ticketId, previousId) => {
        if (!ticketId) {
            stop();

            return;
        }

        if (ticketId !== previousId) {
            resetPollCursor();
        }

        syncPolling();
    }, { immediate: true });

    onUnmounted(() => {
        stop();
        unsubscribeConnection?.();
    });

    return { poll, resetPollCursor };
}
