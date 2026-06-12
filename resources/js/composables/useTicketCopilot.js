import { ref } from 'vue';
import { csrfHeaders } from '../support/csrf.js';

export function useTicketCopilot(basePath) {
    const messages = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const articles = ref([]);
    const provider = ref(null);

    const jsonHeaders = () => ({
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...csrfHeaders(),
    });

    const loadHistory = async () => {
        error.value = null;

        try {
            const res = await fetch(`${basePath}/copilot`, {
                headers: jsonHeaders(),
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                error.value = data.message || 'Failed to load copilot history.';
                return;
            }

            messages.value = data.messages ?? [];
            provider.value = data.provider ?? null;
        } catch {
            error.value = 'Failed to load copilot history.';
        }
    };

    const sendMessage = async (message) => {
        const trimmed = message.trim();

        if (!trimmed || loading.value) {
            return null;
        }

        loading.value = true;
        error.value = null;

        messages.value.push({
            id: `local-${Date.now()}`,
            role: 'user',
            content: trimmed,
        });

        try {
            const res = await fetch(`${basePath}/copilot`, {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ message: trimmed }),
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                error.value = data.message || data.errors?.message?.[0] || 'Copilot request failed.';
                messages.value = messages.value.filter((item) => !String(item.id).startsWith('local-'));
                return null;
            }

            if (data.message) {
                messages.value.push(data.message);
            }

            articles.value = data.articles ?? [];
            provider.value = data.source ?? provider.value;

            return data.message?.content ?? null;
        } catch {
            error.value = 'Copilot request failed.';
            messages.value = messages.value.filter((item) => !String(item.id).startsWith('local-'));
            return null;
        } finally {
            loading.value = false;
        }
    };

    const clearHistory = async () => {
        loading.value = true;
        error.value = null;

        try {
            const res = await fetch(`${basePath}/copilot`, {
                method: 'DELETE',
                headers: jsonHeaders(),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                error.value = data.message || 'Failed to clear copilot history.';
                return;
            }

            messages.value = [];
            articles.value = [];
        } catch {
            error.value = 'Failed to clear copilot history.';
        } finally {
            loading.value = false;
        }
    };

    return {
        messages,
        loading,
        error,
        articles,
        provider,
        loadHistory,
        sendMessage,
        clearHistory,
    };
}
