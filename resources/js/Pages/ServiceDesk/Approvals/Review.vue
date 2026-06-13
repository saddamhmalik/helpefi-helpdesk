<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../../Layouts/AgentLayout.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    approval: Object,
    step: Object,
    canDecide: Boolean,
    loginUrl: String,
});

const { t } = useI18n();

const form = useForm({ note: '', step: props.step?.id, approver: props.step?.approver_user_id });

const approve = () => form.post(`/approvals/email/${props.approval.id}/approve`);

const reject = () => form.post(`/approvals/email/${props.approval.id}/reject`);
</script>

<template>
    <Head :title="$t('service_desk.review_approval')" />
    <AgentLayout>
        <div class="mx-auto max-w-xl rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 shadow-sm">
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $t('service_desk.approval_request') }}</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ approval.subject }}</p>

            <div v-if="canDecide" class="mt-6 space-y-4">
                <textarea v-model="form.note" rows="3" :placeholder="$t('service_desk.optional_note')" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white" @click="approve">{{ $t('service_desk.approve') }}</button>
                    <button type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white" @click="reject">{{ $t('service_desk.reject') }}</button>
                </div>
            </div>

            <div v-else class="mt-6 rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
                <p>{{ $t('service_desk.sign_in_as_the_assigned_approver_to_decide_this_request') }}</p>
                <a v-if="loginUrl" :href="loginUrl" class="mt-2 inline-block font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('service_desk.sign_in') }}</a>
            </div>
        </div>
    </AgentLayout>
</template>
