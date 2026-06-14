import { reactive } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { getSharedRealtimeClient, isRealtimeConfigured } from '../lib/realtimeClient.js';
import { userChannel } from '../lib/realtimeChannels.js';
import { useToast } from './useToast.js';

const state = reactive({
    unread_count: 0,
    recent: [],
    initialized: false,
});

let pollTimer = null;
let realtimeHandler = null;
let subscribedChannel = null;
let lastFetchAt = 0;

function applySummary(summary) {
    if (!summary || typeof summary !== 'object') {
        return;
    }

    state.unread_count = summary.unread_count ?? 0;
    state.recent = Array.isArray(summary.recent) ? summary.recent : [];
    state.initialized = true;
    lastFetchAt = Date.now();
}

function prependNotification(notification, toast) {
    if (!notification?.id) {
        return;
    }

    const isNew = !state.recent.some((item) => item.id === notification.id);

    state.recent = [
        notification,
        ...state.recent.filter((item) => item.id !== notification.id),
    ].slice(0, 8);

    if (isNew && !notification.read_at) {
        state.unread_count += 1;
        toast.info(notification.message, 6000);
    }
}

async function fetchSummary() {
    try {
        const response = await fetch('/notifications/summary', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        applySummary(await response.json());
    } catch {
    }
}

function startPolling() {
    if (pollTimer) {
        return;
    }

    pollTimer = window.setInterval(() => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        fetchSummary();
    }, 45000);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function subscribeRealtime(page, toast) {
    const client = getSharedRealtimeClient(page.props.realtime);
    const channel = userChannel(page.props.tenantId, page.props.auth?.user?.id);

    if (!client || !channel || !isRealtimeConfigured(page.props.realtime)) {
        return;
    }

    if (subscribedChannel && realtimeHandler) {
        client.unsubscribe(subscribedChannel, realtimeHandler);
    }

    realtimeHandler = (payload) => {
        if (payload.event !== 'notification.created') {
            return;
        }

        const notification = payload.data?.notification;

        if (notification) {
            prependNotification(notification, toast);
        } else if (Date.now() - lastFetchAt > 2000) {
            fetchSummary();
        }
    };

    subscribedChannel = channel;
    client.subscribe(channel, realtimeHandler);
}

export function useNotificationInbox() {
    const page = usePage();
    const toast = useToast();

    const init = () => {
        if (!page.props.auth?.user) {
            return;
        }

        if (!state.initialized) {
            fetchSummary();
        }

        subscribeRealtime(page, toast);
        startPolling();
    };

    const refresh = () => fetchSummary();

    const markItemReadLocally = (id) => {
        const item = state.recent.find((entry) => entry.id === id);

        if (item && !item.read_at) {
            item.read_at = new Date().toISOString();
            state.unread_count = Math.max(0, state.unread_count - 1);
        }
    };

    const markAllReadLocally = () => {
        state.recent = state.recent.map((item) => ({
            ...item,
            read_at: item.read_at ?? new Date().toISOString(),
        }));
        state.unread_count = 0;
    };

    return {
        summary: state,
        init,
        refresh,
        markItemReadLocally,
        markAllReadLocally,
        stopPolling,
    };
}
