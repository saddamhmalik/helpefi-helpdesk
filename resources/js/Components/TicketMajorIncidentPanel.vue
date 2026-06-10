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
    if (status === 'active') return 'bg-red-100 text-red-800';
    if (status === 'resolved') return 'bg-amber-100 text-amber-800';
    if (status === 'closed') return 'bg-slate-100 text-slate-700';

    return 'bg-slate-100 text-slate-700';
};
</script>

<template>
    <section v-if="canDeclare || majorIncident" class="rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-900">{{ $t('components.major_incident') }}</h3>
            <p v-if="majorIncident" class="text-xs capitalize text-slate-500">{{ majorIncident.status }}</p>
            <p v-else class="text-xs text-slate-500">{{ $t('components.escalate_critical_service_outages') }}</p>
        </div>

        <div class="space-y-3 p-4">
            <div v-if="majorIncident" class="space-y-3">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusClass(majorIncident.status)">
                    {{ majorIncident.status }}
                </span>

                <dl class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <dt class="text-slate-500">{{ $t('components.declared') }}</dt>
                        <dd class="font-medium text-slate-900">{{ majorIncident.declared_by || $t('components.em_dash') }}</dd>
                    </div>
                    <div v-if="majorIncident.resolved_by">
                        <dt class="text-slate-500">{{ $t('components.resolved_by') }}</dt>
                        <dd class="font-medium text-slate-900">{{ majorIncident.resolved_by }}</dd>
                    </div>
                </dl>

                <Link
                    :href="majorIncident.war_room_url"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-3 py-2 text-xs font-medium text-white hover:bg-red-700"
                >
                    {{ $t('components.open_war_room') }}
                </Link>
            </div>

            <div v-else-if="canDeclare">
                <p class="text-sm text-slate-600">{{ $t('components.flag_this_incident_for_coordinated_response_and_post-incident_review') }}</p>
                <button
                    type="button"
                    class="mt-3 w-full rounded-lg bg-red-600 px-3 py-2 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                    :disabled="declareForm.processing"
                    @click="declare"
                >
                    {{ $t('components.declare_major_incident') }}
                </button>
            </div>
        </div>
    </section>
</template>
