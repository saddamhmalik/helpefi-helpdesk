export function ticketChannel(tenantId, ticketId) {
    if (!ticketId) {
        return null;
    }

    return tenantId ? `${tenantId}.ticket.${ticketId}` : `ticket.${ticketId}`;
}

export function workspaceChannel(tenantId) {
    return tenantId ? `${tenantId}.workspace` : 'workspace';
}

export function userChannel(tenantId, userId) {
    if (!userId) {
        return null;
    }

    return tenantId ? `${tenantId}.user.${userId}` : `user.${userId}`;
}
