<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { formInputClass } from '../composables/useFormControls.js';

const props = defineProps({
    contactId: { type: [Number, String], default: '' },
    requesterEmail: { type: String, default: '' },
    requesterName: { type: String, default: '' },
    initialContact: { type: Object, default: null },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:contactId', 'update:requesterEmail', 'update:requesterName']);

const query = ref('');
const open = ref(false);
const loading = ref(false);
const results = ref([]);
const selected = ref(null);
const showNameField = ref(false);

let debounceTimer = null;
let abortController = null;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const displayLabel = computed(() => {
    if (selected.value) {
        return selected.value.name || selected.value.email;
    }

    return '';
});

const hasSelection = computed(() => Boolean(selected.value));

const syncFromProps = () => {
    if (props.contactId && props.initialContact) {
        selected.value = {
            id: props.initialContact.id,
            name: props.initialContact.name,
            email: props.initialContact.email,
        };
        showNameField.value = false;

        return;
    }

    if (props.requesterEmail) {
        selected.value = {
            id: null,
            name: props.requesterName || props.requesterEmail.split('@')[0],
            email: props.requesterEmail,
        };
        showNameField.value = Boolean(props.requesterName);
    }
};

const fetchResults = async (value) => {
    if (abortController) {
        abortController.abort();
    }

    const term = value.trim();

    if (term.length < 2) {
        loading.value = false;
        results.value = [];
        return;
    }

    abortController = new AbortController();
    loading.value = true;

    try {
        const response = await fetch(`/contacts/search?q=${encodeURIComponent(term)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            signal: abortController.signal,
        });

        if (!response.ok) {
            results.value = [];
            return;
        }

        const data = await response.json();
        results.value = data.results ?? [];
    } catch (error) {
        if (error.name !== 'AbortError') {
            results.value = [];
        }
    } finally {
        loading.value = false;
    }
};

const selectContact = (contact) => {
    selected.value = contact;
    query.value = '';
    open.value = false;
    showNameField.value = false;
    emit('update:contactId', contact.id);
    emit('update:requesterEmail', '');
    emit('update:requesterName', '');
};

const selectNewEmail = (email) => {
    selected.value = {
        id: null,
        name: email.split('@')[0],
        email,
    };
    query.value = '';
    open.value = false;
    showNameField.value = true;
    emit('update:contactId', '');
    emit('update:requesterEmail', email);
    emit('update:requesterName', selected.value.name);
};

const clearSelection = () => {
    selected.value = null;
    query.value = '';
    showNameField.value = false;
    emit('update:contactId', '');
    emit('update:requesterEmail', '');
    emit('update:requesterName', '');
};

const onInput = () => {
    open.value = true;

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => fetchResults(query.value), 200);
};

const onFocus = () => {
    open.value = true;

    if (query.value.trim().length >= 2) {
        fetchResults(query.value);
    }
};

const onBlur = () => {
    setTimeout(() => {
        open.value = false;
    }, 150);
};

const onNameInput = (event) => {
    emit('update:requesterName', event.target.value);
};

const trimmedQuery = computed(() => query.value.trim());
const canUseNewEmail = computed(() => emailPattern.test(trimmedQuery.value));

watch(
    () => [props.contactId, props.requesterEmail, props.initialContact],
    syncFromProps,
    { immediate: true },
);

onMounted(() => {
    syncFromProps();
});

onUnmounted(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    if (abortController) {
        abortController.abort();
    }
});
</script>

<template>
    <div class="relative">
        <div v-if="hasSelection" class="flex items-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2">
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-900">{{ displayLabel }}</p>
                <p v-if="selected.email" class="truncate text-xs text-slate-500">{{ selected.email }}</p>
            </div>
            <button type="button" class="shrink-0 text-xs text-slate-500 hover:text-slate-700" @click="clearSelection">
                Clear
            </button>
        </div>

        <template v-else>
            <input
                v-model="query"
                type="text"
                :class="formInputClass"
                placeholder="Search by name or email…"
                autocomplete="off"
                @input="onInput"
                @focus="onFocus"
                @blur="onBlur"
            />

            <div
                v-if="open && (loading || results.length || canUseNewEmail)"
                class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
            >
                <p v-if="loading" class="px-3 py-2 text-xs text-slate-500">Searching…</p>
                <button
                    v-for="contact in results"
                    :key="contact.id"
                    type="button"
                    class="flex w-full flex-col items-start px-3 py-2 text-left hover:bg-slate-50"
                    @mousedown.prevent="selectContact(contact)"
                >
                    <span class="text-sm font-medium text-slate-900">{{ contact.name || contact.email }}</span>
                    <span v-if="contact.email" class="text-xs text-slate-500">{{ contact.email }}</span>
                </button>
                <button
                    v-if="canUseNewEmail"
                    type="button"
                    class="w-full border-t border-slate-100 px-3 py-2 text-left text-sm text-blue-600 hover:bg-blue-50"
                    @mousedown.prevent="selectNewEmail(trimmedQuery)"
                >
                    Use {{ trimmedQuery }}
                </button>
            </div>
        </template>

        <div v-if="showNameField && selected?.email && !selected?.id" class="mt-2">
            <label class="mb-1 block text-xs font-medium text-slate-600">Requester name</label>
            <input
                :value="requesterName || selected.name"
                type="text"
                :class="formInputClass"
                placeholder="Display name"
                @input="onNameInput"
            />
        </div>

        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
