import { ref, watch } from 'vue';
import { csrfHeaders } from '../support/csrf.js';

const emptyPanels = () => ({
    csat: null,
    lifecycle: [],
    sideConversations: [],
    timeTracking: { total_minutes: 0, entries: [] },
    externalIssues: [],
    loaded: false,
    loading: false,
});

export function useTicketLazyPanels(ticketId) {
    const panels = ref(emptyPanels());

    const load = async (id) => {
        if (!id) {
            panels.value = emptyPanels();
            return;
        }

        if (panels.value.loading) {
            return;
        }

        panels.value.loading = true;

        try {
            const response = await fetch(`/tickets/${id}/panels`, {
                headers: {
                    Accept: 'application/json',
                    ...csrfHeaders(),
                },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            panels.value = {
                ...emptyPanels(),
                ...data,
                loaded: true,
                loading: false,
            };
        } catch {
            panels.value.loading = false;
        }
    };

    watch(ticketId, (id) => {
        panels.value = emptyPanels();
        load(id);
    }, { immediate: true });

    return {
        panels,
        reloadPanels: () => load(ticketId.value),
    };
}
