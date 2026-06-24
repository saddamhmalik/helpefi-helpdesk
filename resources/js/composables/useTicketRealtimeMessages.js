import { onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { getSharedRealtimeClient } from '../lib/realtimeClient.js';
import { ticketChannel } from '../lib/realtimeChannels.js';
import { fetchTicketRealtimeToken } from '../lib/realtimeTokens.js';

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
            client.unsubscribe(ticketChannel(page.props.tenantId, subscribedTicketId), handler);
        }

        subscribedTicketId = null;
        handler = null;
    };

    const subscribe = async (ticketId) => {
        unsubscribe();

        const client = getSharedRealtimeClient(page.props.realtime);

        if (!client || !ticketId) {
            return;
        }

        const credentials = await fetchTicketRealtimeToken(ticketId);

        if (!credentials?.token) {
            return;
        }

        handler = (payload) => {
            if (payload.event === 'message.created') {
                appendMessage(payload.data?.message);
            }
        };

        subscribedTicketId = ticketId;
        client.subscribe(credentials.channel, handler, credentials.token);
    };

    watch(ticketIdRef, (ticketId) => {
        subscribe(ticketId);
    }, { immediate: true });

    onUnmounted(unsubscribe);

    return { appendMessage };
}
