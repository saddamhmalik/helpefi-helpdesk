import { computed, ref, shallowRef } from 'vue';

export function useAsyncState(asyncFn, options = {}) {
    const {
        immediate = false,
        initialData = null,
        resetOnExecute = true,
        isEmpty = (value) => {
            if (value === null || value === undefined) {
                return true;
            }

            if (Array.isArray(value)) {
                return value.length === 0;
            }

            if (typeof value === 'object') {
                return Object.keys(value).length === 0;
            }

            return false;
        },
    } = options;

    const data = shallowRef(initialData);
    const loading = ref(false);
    const error = ref(null);
    const executed = ref(false);

    const empty = computed(() => executed.value && !loading.value && !error.value && isEmpty(data.value));

    const execute = async (...args) => {
        loading.value = true;
        error.value = null;

        if (resetOnExecute) {
            data.value = initialData;
        }

        try {
            data.value = await asyncFn(...args);
            executed.value = true;

            return data.value;
        } catch (e) {
            error.value = e?.message || String(e);
            data.value = initialData;
            executed.value = true;

            throw e;
        } finally {
            loading.value = false;
        }
    };

    const refresh = (...args) => execute(...args);

    const reset = () => {
        data.value = initialData;
        loading.value = false;
        error.value = null;
        executed.value = false;
    };

    if (immediate) {
        execute();
    }

    return {
        data,
        loading,
        error,
        empty,
        executed,
        execute,
        refresh,
        reset,
    };
}
