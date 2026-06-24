export async function fetchTicketRealtimeToken(ticketId, basePath = '/workspace/tickets') {
    if (!ticketId) {
        return null;
    }

    const response = await fetch(`${basePath}/${ticketId}/realtime-token`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        return null;
    }

    return response.json();
}
