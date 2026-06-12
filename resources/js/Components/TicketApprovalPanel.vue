<script setup>
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    approval: Object,
    canDecide: Boolean,
});

const form = useForm({ note: '' });

const stepLabel = (step) => {
    if (step.status === 'approved') {
        return t('components.approval_approved');
    }

    if (step.status === 'rejected') {
        return t('components.approval_rejected');
    }

    if (step.step_order === props.approval.current_step) {
        return t('components.approval_waiting');
    }

    return t('components.approval_queued');
};

const stepClass = (step) => {
    if (step.status === 'approved') {
        return 'border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40';
    }

    if (step.status === 'rejected') {
        return 'border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40';
    }

    if (step.step_order === props.approval.current_step) {
        return 'border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40';
    }

    return 'border-slate-200 dark:border-slate-800 bg-slate-50';
};

const approve = () => {
    form.post(`/service-desk/approvals/${props.approval.id}/approve`, { preserveScroll: true });
};

const reject = () => {
    form.post(`/service-desk/approvals/${props.approval.id}/reject`, { preserveScroll: true });
};
</script>

<template>
    <section v-if="approval" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <div class="border-b border-slate-100 dark:border-slate-800 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('components.approval') }}</h3>
            <p class="text-xs capitalize text-slate-500 dark:text-slate-400">{{ approval.status }}</p>
        </div>

        <div class="space-y-2 p-4">
            <div
                v-for="step in approval.steps"
                :key="step.id"
                class="rounded-lg border px-3 py-2 text-sm"
                :class="stepClass(step)"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ step.approver?.name }}</span>
                    <span class="text-xs text-slate-600 dark:text-slate-400">{{ stepLabel(step) }}</span>
                </div>
            </div>
        </div>

        <div v-if="canDecide" class="border-t border-slate-100 dark:border-slate-800 p-4 space-y-3">
            <textarea v-model="form.note" rows="2" :placeholder="$t('components.optional_note')" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
            <div class="flex gap-2">
                <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white" @click="approve">{{ $t('components.approve') }}</button>
                <button type="button" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white" @click="reject">{{ $t('components.reject') }}</button>
            </div>
        </div>
    </section>
</template>
