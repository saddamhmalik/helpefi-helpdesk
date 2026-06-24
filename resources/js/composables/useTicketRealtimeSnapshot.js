import { onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { getSharedRealtimeClient } from '../lib/realtimeClient.js';
import { ticketChannel } from '../lib/realtimeChannels.js';
import { fetchTicketRealtimeToken } from '../lib/realtimeTokens.js';

export function useTicketRealtimeSnapshot(ticketIdRef, onUpdate) {
    const page = usePage();
    let subscribedTicketId = null;
    let handler = null;

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

        if (!client || !ticketId || typeof onUpdate !== 'function') {
            return;
        }

        const credentials = await fetchTicketRealtimeToken(ticketId);

        if (!credentials?.token) {
            return;
        }

        handler = (payload) => {
            if (payload.event === 'ticket.updated' && payload.data?.ticket) {
                onUpdate(payload.data.ticket);
            }
        };

        subscribedTicketId = ticketId;
        client.subscribe(credentials.channel, handler, credentials.token);
    };

    watch(ticketIdRef, (ticketId) => {
        subscribe(ticketId);
    }, { immediate: true });

    onUnmounted(unsubscribe);
}
