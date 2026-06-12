<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import CsatRatingForm from '../../Components/CsatRatingForm.vue';

defineProps({
    ticket: Object,
    csat: Object,
    submitUrl: String,
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('portal.feedback_for', { number: ticket.number })" />
    <PortalLayout>
        <div class="mx-auto max-w-xl">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $t('portal.how_did_we_do') }}</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ $t('portal.your_request') }} <span class="font-medium text-slate-900 dark:text-slate-100">{{ ticket.number }}</span>
                — {{ ticket.subject }}
            </p>

            <div v-if="usePage().props.flash?.success" class="mt-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-900">
                {{ usePage().props.flash.success }}
            </div>

            <div class="mt-6 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                <CsatRatingForm
                    :csat="csat"
                    :ticket="ticket"
                    :action="submitUrl"
                />
            </div>
        </div>
    </PortalLayout>
</template>
