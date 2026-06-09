import { onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { getSharedRealtimeClient } from '../lib/realtimeClient.js';

export function useTicketRealtimeMessages(ticketIdRef, messagesRef) {
    const page = usePage();
    let subscribedTicketId = null;
    let handler = null;

    const normalizeMessage = (message) => {
        if (!message?.id) {
            return null;
        }

        if (message.user || message.contact) {
            return message;
        }

        if (message.author_type === 'agent') {
            return {
                ...message,
                user_id: message.user_id ?? message.user?.id ?? null,
                user: message.user ?? { name: message.author_name ?? 'Agent' },
            };
        }

        return {
            ...message,
            contact_id: message.contact_id ?? null,
            contact: message.contact ?? { name: message.author_name ?? 'Visitor' },
        };
    };

    const appendMessage = (message) => {
        const normalized = normalizeMessage(message);

        if (!normalized || normalized.ticket_id !== ticketIdRef.value) {
            return;
        }

        if (messagesRef.value.some((item) => item.id === normalized.id)) {
            return;
        }

        messagesRef.value = [...messagesRef.value, normalized];
    };

    const unsubscribe = () => {
        const client = getSharedRealtimeClient(page.props.realtime);

        if (client && subscribedTicketId && handler) {
            client.unsubscribe(`ticket.${subscribedTicketId}`, handler);
        }

        subscribedTicketId = null;
        handler = null;
    };

    const subscribe = (ticketId) => {
        unsubscribe();

        const client = getSharedRealtimeClient(page.props.realtime);

        if (!client || !ticketId) {
            return;
        }

        handler = (payload) => {
            if (payload.event === 'message.created') {
                appendMessage(payload.data?.message);
            }
        };

        subscribedTicketId = ticketId;
        client.subscribe(`ticket.${ticketId}`, handler);
    };

    watch(ticketIdRef, (ticketId) => {
        subscribe(ticketId);
    }, { immediate: true });

    onUnmounted(unsubscribe);

    return { appendMessage };
}
