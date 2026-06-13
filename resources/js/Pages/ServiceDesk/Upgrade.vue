<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { useCurrency } from '../../composables/useCurrency.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    plan: String,
    features: Array,
    addon: Object,
    onTrial: { type: Boolean, default: false },
    canPurchase: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const { formatPrice } = useCurrency(() => props.addon?.currency ?? page.props.billing?.currency);

const purchaseAddon = () => {
    router.post('/settings/billing/addons/service_desk', {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('service_desk.service_desk')" />
    <AgentLayout>
        <PageHeader :title="$t('service_desk.service_desk')" :description="$t('service_desk.enterprise_itsm_add-on_for_structured_incident_request_change_and_prob')" />

        <div class="mx-auto max-w-3xl rounded-2xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 p-8 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Paid add-on</p>
            <h2 class="mt-2 text-2xl font-semibold text-amber-950 dark:text-amber-100">Unlock Service Desk</h2>
            <p class="mt-3 text-sm leading-6 text-amber-900/90 dark:text-amber-200/90">
                You are on the <span class="font-medium">{{ plan }}</span> plan. Service Desk is a monthly add-on that adds ITIL-type queues,
                approval workflows, change and problem management, and major incident war rooms on top of your existing tickets and service catalog.
            </p>

            <p v-if="onTrial" class="mt-4 rounded-lg border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 px-4 py-3 text-sm text-blue-900 dark:text-blue-200">
                You can enable Service Desk during your free trial. After the trial ends, you will need an active paid plan and this add-on at
                <span v-if="addon?.price_monthly" class="font-semibold">{{ formatPrice(addon.price_monthly) }}/mo</span>
                <span v-else class="font-semibold">the listed monthly price</span>.
            </p>

            <p v-else-if="addon?.price_monthly" class="mt-4 text-lg font-semibold text-amber-950 dark:text-amber-100">
                {{ formatPrice(addon.price_monthly) }}/month
            </p>

            <ul class="mt-6 space-y-2 text-sm text-amber-950/90 dark:text-amber-200/90">
                <li class="flex gap-2">
                    <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-600" />
                    Separate queues for incidents, service requests, changes, and problems
                </li>
                <li class="flex gap-2">
                    <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-600" />
                    Catalog and change approval inbox with email and in-app actions
                </li>
                <li class="flex gap-2">
                    <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-600" />
                    Change calendar, problem linking, and major incident war rooms with post-incident review
                </li>
            </ul>

            <div class="mt-8 flex flex-wrap gap-3">
                <button
                    v-if="canPurchase"
                    type="button"
                    class="inline-flex items-center rounded-lg bg-slate-900 dark:bg-amber-500 px-4 py-2.5 text-sm font-medium text-white dark:text-amber-950 hover:bg-slate-800 dark:hover:bg-amber-400"
                    @click="purchaseAddon"
                >
                    {{ onTrial ? 'Enable for trial' : 'Add Service Desk' }}{{ addon?.price_monthly && !onTrial ? ` — ${formatPrice(addon.price_monthly)}/mo` : '' }}
                </button>
                <Link
                    v-else
                    href="/settings/billing?section=plans"
                    class="inline-flex items-center rounded-lg bg-slate-900 dark:bg-amber-500 px-4 py-2.5 text-sm font-medium text-white dark:text-amber-950 hover:bg-slate-800 dark:hover:bg-amber-400"
                >
                    Choose a plan to continue
                </Link>
                <Link
                    href="/settings/billing?section=addons"
                    class="inline-flex items-center rounded-lg border border-amber-300 dark:border-amber-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-medium text-amber-950 dark:text-amber-200 hover:bg-amber-100/60 dark:hover:bg-slate-800"
                >
                    View billing add-ons
                </Link>
                <Link
                    href="/settings/service-catalog"
                    class="inline-flex items-center rounded-lg border border-amber-300 dark:border-amber-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-medium text-amber-950 dark:text-amber-200 hover:bg-amber-100/60 dark:hover:bg-slate-800"
                >
                    Manage service catalog
                </Link>
            </div>
        </div>
    </AgentLayout>
</template>
