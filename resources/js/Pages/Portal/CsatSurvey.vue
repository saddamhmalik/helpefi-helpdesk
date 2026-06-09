<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import CsatRatingForm from '../../Components/CsatRatingForm.vue';

defineProps({
    ticket: Object,
    csat: Object,
    submitUrl: String,
});
</script>

<template>
    <Head :title="`Feedback for ${ticket.number}`" />
    <PortalLayout>
        <div class="mx-auto max-w-xl">
            <h1 class="text-2xl font-bold text-slate-900">How did we do?</h1>
            <p class="mt-1 text-sm text-slate-600">
                Your request <span class="font-medium text-slate-900">{{ ticket.number }}</span>
                — {{ ticket.subject }}
            </p>

            <div v-if="usePage().props.flash?.success" class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ usePage().props.flash.success }}
            </div>

            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <CsatRatingForm
                    :csat="csat"
                    :ticket="ticket"
                    :action="submitUrl"
                />
            </div>
        </div>
    </PortalLayout>
</template>
