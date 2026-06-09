<script setup>
import { useForm } from '@inertiajs/vue3';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';

const props = defineProps({
    billing: Object,
});

const { activeSection } = useSettingsSection({
    defaultSection: 'usage',
    sections: ['usage', 'plans', 'features'],
});

const form = useForm({
    plan: props.billing.plan.slug,
});

const savePlan = () => {
    form.put('/settings/billing/plan', { preserveScroll: true });
};

const usagePercent = (key) => {
    const limit = props.billing.limits[key];
    const used = props.billing.usage[key === 'agents' ? 'agents' : 'tickets_monthly'];
    if (limit === 'unlimited') {
        return 0;
    }
    return Math.min(100, Math.round((used / limit) * 100));
};

const hasFeature = (feature) => props.billing.features.includes(feature);
</script>

<template>
    <SettingsLayout
        title="Billing & plan"
        description="Current subscription, usage limits, and plan features."
    >
        <div v-show="activeSection === 'usage'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-medium text-slate-900">Current plan</h2>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-3xl font-semibold text-slate-900">{{ billing.plan.name }}</span>
                        <span class="text-sm text-slate-500">${{ billing.plan.price }}/mo</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">
                        Status:
                        <span class="font-medium capitalize">{{ billing.status.replace('_', ' ') }}</span>
                    </p>
                    <p v-if="billing.renews_at" class="mt-1 text-sm text-slate-500">
                        Renews {{ new Date(billing.renews_at).toLocaleDateString() }}
                    </p>

                    <div class="mt-6 space-y-4">
                        <div>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-700">Team members</span>
                                <span class="text-slate-500">
                                    {{ billing.usage.agents }}
                                    <template v-if="billing.usage.pending_invites"> (+{{ billing.usage.pending_invites }} pending)</template>
                                    / {{ billing.limits.agents }}
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-blue-600 transition-all" :style="{ width: usagePercent('agents') + '%' }" />
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-700">Tickets this month</span>
                                <span class="text-slate-500">{{ billing.usage.tickets_monthly }} / {{ billing.limits.tickets_monthly }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-emerald-600 transition-all" :style="{ width: usagePercent('tickets_monthly') + '%' }" />
                            </div>
                        </div>
                    </div>
                </div>

        <div v-show="activeSection === 'plans'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-medium text-slate-900">Change plan</h2>
                    <p class="mt-1 text-sm text-slate-500">Simulated plan switch for self-hosted deployments (no payment processor).</p>

                    <form class="mt-4 space-y-3" @submit.prevent="savePlan">
                        <label
                            v-for="plan in billing.available_plans"
                            :key="plan.slug"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition"
                            :class="form.plan === plan.slug ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'"
                        >
                            <input v-model="form.plan" type="radio" :value="plan.slug" class="mt-1" />
                            <div class="flex-1">
                                <div class="flex items-baseline justify-between">
                                    <span class="font-medium text-slate-900">{{ plan.name }}</span>
                                    <span class="text-sm text-slate-500">${{ plan.price }}/mo</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ plan.limits.agents }} agents · {{ plan.limits.tickets_monthly }} tickets/mo
                                </p>
                                <p v-if="plan.features.length" class="mt-2 text-xs text-slate-600">
                                    Includes: {{ plan.features.join(', ') }}
                                </p>
                            </div>
                        </label>

                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">
                            Update plan
                        </button>
                    </form>
                </div>

        <div v-show="activeSection === 'features'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-medium text-slate-900">Feature access</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="feature in ['automation', 'service_catalog', 'ai', 'integrations', 'assets', 'channels', 'sla', 'workspace']"
                            :key="feature"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                            :class="hasFeature(feature) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-500'"
                        >
                            <span>{{ feature.replace('_', ' ') }}</span>
                            <span class="ml-auto text-xs font-medium">{{ hasFeature(feature) ? 'Included' : 'Locked' }}</span>
                        </div>
                    </div>
                </div>
    </SettingsLayout>
</template>
