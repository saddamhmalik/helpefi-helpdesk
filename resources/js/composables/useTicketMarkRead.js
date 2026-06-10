import { appFetch } from '../support/http.js';

export async function markTicketRead(ticketId, messageId = null) {
    if (!ticketId) {
        return;
    }

    const body = messageId ? JSON.stringify({ message_id: messageId }) : '{}';

    await appFetch(`/workspace/tickets/${ticketId}/read`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body,
    }).catch(() => {});
}

export function clearQueueUnread(queueItems, ticketId) {
    const item = queueItems.find((entry) => entry.id === ticketId);

    if (item) {
        item.unread_count = 0;
    }
}

export function applyUnreadCounts(queueItems, unreadCounts = {}) {
    queueItems.forEach((item) => {
        if (Object.prototype.hasOwnProperty.call(unreadCounts, item.id)) {
            item.unread_count = unreadCounts[item.id];
        }
    });
}

export function bumpQueueUnread(queueItems, ticketId) {
    const item = queueItems.find((entry) => entry.id === ticketId);

    if (item) {
        item.unread_count = (item.unread_count ?? 0) + 1;
    }
}
