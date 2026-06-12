<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    ticketId: Number,
    changeRecord: Object,
    agents: Array,
    riskOptions: Array,
});

const form = useForm({
    risk: props.changeRecord?.risk ?? 'medium',
    impact: props.changeRecord?.impact ?? '',
    rollback_plan: props.changeRecord?.rollback_plan ?? '',
    planned_start: props.changeRecord?.planned_start?.slice(0, 16) ?? '',
    planned_end: props.changeRecord?.planned_end?.slice(0, 16) ?? '',
    cab_user_ids: props.changeRecord?.cab_user_ids ?? [],
    cab_notes: props.changeRecord?.cab_notes ?? '',
    implementation_notes: props.changeRecord?.implementation_notes ?? '',
});

const cabMembers = computed(() => {
    const ids = new Set((form.cab_user_ids ?? []).map(Number));

    return (props.agents ?? []).filter((agent) => ids.has(Number(agent.id)));
});

const toggleCabMember = (agentId) => {
    const ids = new Set((form.cab_user_ids ?? []).map(Number));
    const id = Number(agentId);

    if (ids.has(id)) {
        ids.delete(id);
    } else {
        ids.add(id);
    }

    form.cab_user_ids = Array.from(ids);
};

const save = () => {
    form.put(`/tickets/${props.ticketId}/change-record`, { preserveScroll: true });
};

const riskClass = (risk) => {
    if (risk === 'critical') return 'text-red-700 dark:text-red-300';
    if (risk === 'high') return 'text-orange-700';
    if (risk === 'low') return 'text-emerald-700 dark:text-emerald-300';

    return 'text-amber-700 dark:text-amber-300';
};
</script>

<template>
    <section class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <div class="border-b border-slate-100 dark:border-slate-800 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('components.change_management') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('components.risk_schedule_and_cab_details') }}</p>
        </div>

        <form class="space-y-3 p-4" @submit.prevent="save">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.risk') }}</label>
                <select v-model="form.risk" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm capitalize" :class="riskClass(form.risk)">
                    <option v-for="option in riskOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.planned_start') }}</label>
                    <input v-model="form.planned_start" type="datetime-local" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.planned_end') }}</label>
                    <input v-model="form.planned_end" type="datetime-local" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.impact') }}</label>
                <textarea v-model="form.impact" rows="2" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.rollback_plan') }}</label>
                <textarea v-model="form.rollback_plan" rows="2" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.cab_members') }}</label>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="agent in agents"
                        :key="agent.id"
                        type="button"
                        class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition"
                        :class="form.cab_user_ids.includes(agent.id)
                            ? 'bg-slate-900 text-white ring-slate-900'
                            : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 ring-slate-200 dark:ring-slate-700 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800'"
                        @click="toggleCabMember(agent.id)"
                    >
                        {{ agent.name }}
                    </button>
                </div>
                <p v-if="cabMembers.length" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('components.cab_selected_count', { count: cabMembers.length }) }}</p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.cab_notes') }}</label>
                <textarea v-model="form.cab_notes" rows="2" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.implementation_notes') }}</label>
                <textarea v-model="form.implementation_notes" rows="2" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
            </div>

            <button
                type="submit"
                class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ $t('components.save_change_details') }}
            </button>
        </form>
    </section>
</template>
