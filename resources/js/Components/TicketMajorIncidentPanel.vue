<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    ticketId: Number,
    majorIncident: Object,
    canDeclare: Boolean,
});

const declareForm = useForm({});

const declare = () => {
    declareForm.post(`/tickets/${props.ticketId}/major-incident`);
};

const statusClass = (status) => {
    if (status === 'active') return 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200';
    if (status === 'resolved') return 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200';
    if (status === 'closed') return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';

    return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
};
</script>

<template>
    <section v-if="canDeclare || majorIncident" class="px-4 py-3">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    {{ $t('components.major_incident') }}
                </p>

                <div v-if="majorIncident" class="mt-1 flex flex-wrap items-center gap-1.5">
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide capitalize" :class="statusClass(majorIncident.status)">
                        {{ majorIncident.status }}
                    </span>
                    <span v-if="majorIncident.declared_by" class="text-xs text-slate-500 dark:text-slate-400">
                        · {{ $t('components.declared') }} {{ majorIncident.declared_by }}
                    </span>
                    <span v-if="majorIncident.resolved_by" class="text-xs text-slate-500 dark:text-slate-400">
                        · {{ majorIncident.resolved_by }}
                    </span>
                </div>

                <p v-else class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    {{ $t('components.escalate_critical_service_outages') }}
                </p>
            </div>

            <Link
                v-if="majorIncident"
                :href="majorIncident.war_room_url"
                class="shrink-0 rounded-md bg-red-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-700"
            >
                {{ $t('components.open_war_room') }}
            </Link>

            <button
                v-else-if="canDeclare"
                type="button"
                class="shrink-0 rounded-md bg-red-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                :disabled="declareForm.processing"
                @click="declare"
            >
                {{ $t('components.declare_major_incident') }}
            </button>
        </div>
    </section>
</template>
