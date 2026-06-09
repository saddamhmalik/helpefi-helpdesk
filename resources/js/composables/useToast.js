import { reactive } from 'vue';

const state = reactive({
    items: [],
});

let nextId = 1;

function push(type, message, duration = null) {
    const text = typeof message === 'string' ? message.trim() : '';

    if (! text) {
        return null;
    }

    const id = nextId++;
    const timeout = duration ?? (type === 'error' ? 8000 : 5000);

    state.items.push({ id, type, message: text });

    if (timeout > 0) {
        setTimeout(() => dismiss(id), timeout);
    }

    return id;
}

function dismiss(id) {
    const index = state.items.findIndex((item) => item.id === id);

    if (index >= 0) {
        state.items.splice(index, 1);
    }
}

export function useToast() {
    return {
        items: state.items,
        success: (message, duration) => push('success', message, duration),
        error: (message, duration) => push('error', message, duration),
        info: (message, duration) => push('info', message, duration),
        dismiss,
    };
}

export function collectErrorMessages(errors) {
    if (! errors || typeof errors !== 'object') {
        return [];
    }

    const messages = [];

    for (const value of Object.values(errors)) {
        if (Array.isArray(value)) {
            messages.push(...value.filter(Boolean));
        } else if (value) {
            messages.push(String(value));
        }
    }

    return [...new Set(messages)];
}
