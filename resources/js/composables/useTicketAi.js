import { ref } from 'vue';

export function useTicketAi(basePath) {
    const aiLoading = ref(null);
    const aiError = ref(null);
    const aiSummary = ref(null);
    const aiArticles = ref([]);

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

    const jsonHeaders = () => ({
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
    });

    const request = async (method, action) => {
        aiLoading.value = action;
        aiError.value = null;

        try {
            const res = await fetch(`${basePath}/${action}`, {
                method,
                headers: jsonHeaders(),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                aiError.value = data.message || 'AI request failed.';
                return null;
            }

            return data;
        } catch {
            aiError.value = 'AI request failed.';
            return null;
        } finally {
            aiLoading.value = null;
        }
    };

    const suggestReply = async () => {
        const data = await request('POST', 'suggest-reply');
        return data?.reply ?? null;
    };

    const summarize = async () => {
        const data = await request('POST', 'summarize');
        if (data?.summary) {
            aiSummary.value = data.summary;
        }
        return data?.summary ?? null;
    };

    const kbAssist = async () => {
        const data = await request('GET', 'kb-assist');
        aiArticles.value = data?.articles ?? [];
        return aiArticles.value;
    };

    const clearAiState = () => {
        aiError.value = null;
        aiSummary.value = null;
        aiArticles.value = [];
    };

    return {
        aiLoading,
        aiError,
        aiSummary,
        aiArticles,
        suggestReply,
        summarize,
        kbAssist,
        clearAiState,
    };
}
