<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../composables/useDateTime.js';
import { formInputClass, formTextareaClass } from '../composables/useFormControls.js';

const { t } = useI18n();
const { formatDateTime } = useDateTime();

const props = defineProps({
    ticketId: { type: Number, required: true },
    timeTracking: { type: Object, default: () => ({ total_minutes: 0, entries: [] }) },
});

const page = usePage();
const showForm = ref(false);

const isAdmin = computed(() => page.props.auth.user?.is_admin ?? false);
const currentUserId = computed(() => page.props.auth.user?.id);

const form = useForm({
    minutes: 15,
    note: '',
    logged_at: '',
});

const formatDuration = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    if (!hours) {
        return `${remainder}m`;
    }

    return remainder ? `${hours}h ${remainder}m` : `${hours}h`;
};

const totalLoggedLabel = computed(() => t('components.time_logged_summary', {
    duration: formatDuration(props.timeTracking.total_minutes ?? 0),
}));

const canDelete = (entry) => isAdmin.value || entry.user?.id === currentUserId.value;

const submit = () => {
    form.post(`/tickets/${props.ticketId}/time-entries`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.minutes = 15;
            showForm.value = false;
        },
    });
};

const removeEntry = (entryId) => {
    router.delete(`/tickets/${props.ticketId}/time-entries/${entryId}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <section class="px-4 py-3">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('components.time_tracking') }}</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    {{ totalLoggedLabel }}
                </p>
            </div>
            <button
                type="button"
                class="rounded-md border border-slate-200 dark:border-slate-800 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800"
                @click="showForm = !showForm"
            >
                {{ showForm ? $t('components.cancel') : $t('components.log_time') }}
            </button>
        </div>

        <form v-if="showForm" class="mt-3 space-y-2 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/70 p-3" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-2">
                <input
                    v-model.number="form.minutes"
                    type="number"
                    min="1"
                    max="1440"
                    required
                    :placeholder="$t('components.minutes')"
                    :class="formInputClass"
                />
                <input v-model="form.logged_at" type="datetime-local" :class="formInputClass" />
            </div>
            <textarea v-model="form.note" rows="2" :placeholder="$t('components.note_optional')" :class="formTextareaClass" />
            <p v-if="form.errors.minutes" class="text-xs text-red-600">{{ form.errors.minutes }}</p>
            <button
                type="submit"
                class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ $t('components.save_entry') }}
            </button>
        </form>

        <ul v-if="timeTracking.entries?.length" class="mt-3 space-y-2">
            <li
                v-for="entry in timeTracking.entries"
                :key="entry.id"
                class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ formatDuration(entry.minutes) }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ entry.user?.name || $t('components.unknown') }} · {{ formatDateTime(entry.logged_at) }}</p>
                        <p v-if="entry.note" class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ entry.note }}</p>
                    </div>
                    <button
                        v-if="canDelete(entry)"
                        type="button"
                        class="shrink-0 rounded px-1.5 py-0.5 text-xs text-slate-400 dark:text-slate-500 hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800 hover:text-red-600"
                        @click="removeEntry(entry.id)"
                    >
                        {{ $t('components.remove') }}
                    </button>
                </div>
            </li>
        </ul>

        <p v-else class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ $t('components.no_time_logged_yet') }}</p>
    </section>
</template>
