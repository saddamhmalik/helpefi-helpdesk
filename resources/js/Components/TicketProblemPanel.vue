<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    ticketId: Number,
    problemRecord: Object,
    incidentCandidates: Array,
});

const form = useForm({
    root_cause: props.problemRecord?.root_cause ?? '',
    workaround: props.problemRecord?.workaround ?? '',
    is_known_error: props.problemRecord?.is_known_error ?? false,
});

const linkForm = useForm({
    incident_ticket_id: '',
});

const showLinkPicker = ref(false);

const save = () => {
    form.put(`/tickets/${props.ticketId}/problem-record`, { preserveScroll: true });
};

const linkIncident = () => {
    if (!linkForm.incident_ticket_id) {
        return;
    }

    linkForm.post(`/tickets/${props.ticketId}/problem-incidents`, {
        preserveScroll: true,
        onSuccess: () => {
            linkForm.reset();
            showLinkPicker.value = false;
        },
    });
};

const unlinkIncident = (incidentId) => {
    useForm({}).delete(`/tickets/${props.ticketId}/problem-incidents/${incidentId}`, { preserveScroll: true });
};
</script>

<template>
    <section class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <div class="border-b border-slate-100 dark:border-slate-800 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('components.problem_management') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('components.root_cause_known_errors_and_linked_incidents') }}</p>
        </div>

        <form class="space-y-3 border-b border-slate-100 dark:border-slate-800 p-4" @submit.prevent="save">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.root_cause') }}</label>
                <textarea v-model="form.root_cause" rows="3" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.workaround') }}</label>
                <textarea v-model="form.workaround" rows="2" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input v-model="form.is_known_error" type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-blue-600" />
                {{ $t('components.known_error') }}
            </label>

            <button
                type="submit"
                class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ $t('components.save_problem_details') }}
            </button>
        </form>

        <div class="p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('components.linked_incidents') }}</h4>
                <button
                    type="button"
                    class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                    @click="showLinkPicker = !showLinkPicker"
                >
                    {{ showLinkPicker ? $t('components.cancel') : $t('components.link_incident') }}
                </button>
            </div>

            <div v-if="showLinkPicker" class="mb-3 flex gap-2">
                <select v-model="linkForm.incident_ticket_id" class="min-w-0 flex-1 rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm">
                    <option value="">{{ $t('components.select_incident_ellipsis') }}</option>
                    <option v-for="incident in incidentCandidates" :key="incident.id" :value="incident.id">
                        {{ incident.number }} — {{ incident.subject }}
                    </option>
                </select>
                <button
                    type="button"
                    class="shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    :disabled="linkForm.processing || !linkForm.incident_ticket_id"
                    @click="linkIncident"
                >
                    {{ $t('components.link') }}
                </button>
            </div>

            <ul v-if="problemRecord?.incidents?.length" class="space-y-2">
                <li
                    v-for="incident in problemRecord.incidents"
                    :key="incident.id"
                    class="flex items-start justify-between gap-2 rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-2"
                >
                    <div class="min-w-0">
                        <Link :href="`/tickets/${incident.id}`" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                            {{ incident.number }}
                        </Link>
                        <p class="truncate text-xs text-slate-600 dark:text-slate-400">{{ incident.subject }}</p>
                        <p v-if="incident.status" class="text-[10px] text-slate-400 dark:text-slate-500">{{ incident.status }}</p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 text-xs text-red-600 hover:text-red-700 dark:text-red-300"
                        @click="unlinkIncident(incident.id)"
                    >
                        {{ $t('components.unlink') }}
                    </button>
                </li>
            </ul>
            <p v-else class="text-sm text-slate-500 dark:text-slate-400">{{ $t('components.no_incidents_linked_yet') }}</p>
        </div>
    </section>
</template>
