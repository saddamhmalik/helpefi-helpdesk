export function ticketStatusBadgeVariant(name) {
    const value = (name || '').toLowerCase();

    if (value.includes('open')) {
        return 'success';
    }

    if (value.includes('pending')) {
        return 'warning';
    }

    if (value.includes('closed') || value.includes('resolved')) {
        return 'default';
    }

    return 'default';
}

export function ticketPriorityBadgeVariant(name) {
    const value = (name || '').toLowerCase();

    if (value.includes('urgent') || value.includes('critical')) {
        return 'error';
    }

    if (value.includes('high')) {
        return 'warning';
    }

    if (value.includes('low')) {
        return 'default';
    }

    return 'info';
}

export function ticketPriorityDotClass(name) {
    const value = (name || '').toLowerCase();

    if (value.includes('urgent') || value.includes('critical')) {
        return 'bg-red-500';
    }

    if (value.includes('high')) {
        return 'bg-orange-500';
    }

    if (value.includes('low')) {
        return 'bg-slate-400';
    }

    return 'bg-blue-500';
}
