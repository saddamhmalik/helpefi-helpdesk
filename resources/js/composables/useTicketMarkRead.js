const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

export async function markTicketRead(ticketId, messageId = null) {
    if (!ticketId) {
        return;
    }

    const body = messageId ? JSON.stringify({ message_id: messageId }) : '{}';

    await fetch(`/workspace/tickets/${ticketId}/read`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
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
