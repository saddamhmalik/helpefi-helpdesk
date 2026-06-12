<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PlatformStatCard from '../../../Components/Platform/PlatformStatCard.vue';
import { usePlatformAdmin } from '../../../composables/usePlatformAdmin.js';
import { useI18n } from 'vue-i18n';

defineProps({
    dashboard: Object,
});

const { t } = useI18n();

const { can, quickLinks } = usePlatformAdmin();

const statusLabel = (workspace) => {
    if (workspace.is_blocked) {
        return 'Blocked';
    }

    if (!workspace.subscription) {
        return 'No subscription';
    }

    if (workspace.subscription.on_trial) {
        return 'Trial';
    }

    if (workspace.subscription.trial_expired) {
        return 'Trial expired';
    }

    return workspace.subscription.status.replace('_', ' ');
};

const statusTone = (workspace) => {
    if (workspace.is_blocked) {
        return 'red';
    }

    if (workspace.subscription?.on_trial) {
        return 'blue';
    }

    if (workspace.subscription?.trial_expired) {
        return 'amber';
    }

    return 'emerald';
};
</script>

<template>
    <Head :title="$t('nav.dashboard')" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <PageHeader
                :title="$t('nav.dashboard')"
                :description="$t('central.overview_of_workspaces_billing_and_platform_configuration')"
            />

            <section v-if="quickLinks.length > 1" class="mb-8">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.quick_access') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="item in quickLinks.filter((link) => link.href !== '/admin/dashboard')"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition hover:border-blue-300 hover:shadow-md"
                    >
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ item.label }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Open {{ item.label.toLowerCase() }}</p>
                    </Link>
                </div>
            </section>

            <section v-if="dashboard.executive_metrics" class="mb-8">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.platform_usage_all_workspaces') }}</h2>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                    Aggregated across {{ dashboard.executive_metrics.workspaces_scanned }} workspace(s). Refreshed every 15 minutes.
                </p>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <PlatformStatCard :label="$t('central.open_tickets')" :value="dashboard.executive_metrics.open_tickets" tone="blue" />
                    <PlatformStatCard :label="$t('central.total_tickets')" :value="dashboard.executive_metrics.total_tickets" />
                    <PlatformStatCard :label="$t('central.tickets_30_days')" :value="dashboard.executive_metrics.tickets_last_30_days" tone="emerald" />
                    <PlatformStatCard :label="$t('central.total_customers')" :value="dashboard.executive_metrics.total_contacts" />
                    <PlatformStatCard :label="$t('central.total_agents')" :value="dashboard.executive_metrics.total_agents" />
                    <PlatformStatCard
                        :label="$t('central.csat_average_30_days')"
                        :value="dashboard.executive_metrics.csat_average_30_days ?? '—'"
                        tone="emerald"
                    />
                    <PlatformStatCard :label="$t('central.csat_responses_30_days')" :value="dashboard.executive_metrics.csat_responses_30_days" />
                </div>
            </section>

            <section v-if="dashboard.workspace_stats" class="mb-8">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.workspaces') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <PlatformStatCard :label="$t('central.total')" :value="dashboard.workspace_stats.total" />
                    <PlatformStatCard :label="$t('central.active_plans')" :value="dashboard.workspace_stats.active" tone="emerald" />
                    <PlatformStatCard :label="$t('central.on_trial')" :value="dashboard.workspace_stats.on_trial" tone="blue" />
                    <PlatformStatCard :label="$t('central.trial_expired')" :value="dashboard.workspace_stats.expired_trial" tone="amber" />
                    <PlatformStatCard :label="$t('central.blocked')" :value="dashboard.workspace_stats.blocked" tone="red" />
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-3">
                <section v-if="dashboard.recent_workspaces?.length" class="lg:col-span-2">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.recent_workspaces') }}</h2>
                        <Link v-if="can('tenants.view')" href="/admin/tenants" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                            View all
                        </Link>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                            <li v-for="workspace in dashboard.recent_workspaces" :key="workspace.id" class="flex items-start justify-between gap-4 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ workspace.name }}</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ workspace.slug }}</p>
                                    <a
                                        v-if="workspace.url"
                                        :href="workspace.url"
                                        target="_blank"
                                        class="mt-1 inline-block text-xs font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                                    >
                                        {{ $t('central.open_workspace') }}
                                    </a>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize"
                                    :class="{
                                        'bg-red-100 text-red-700 dark:text-red-300': statusTone(workspace) === 'red',
                                        'bg-blue-100 text-blue-700 dark:text-blue-300': statusTone(workspace) === 'blue',
                                        'bg-amber-100 text-amber-800': statusTone(workspace) === 'amber',
                                        'bg-emerald-100 text-emerald-700 dark:text-emerald-300': statusTone(workspace) === 'emerald',
                                    }"
                                >
                                    {{ statusLabel(workspace) }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.billing') }}</h2>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                            Stripe is
                            <span class="font-medium" :class="dashboard.stripe_enabled ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                                {{ dashboard.stripe_enabled ? 'connected' : 'not configured' }}
                            </span>
                            for tenant checkout.
                        </p>
                        <div v-if="dashboard.payment_stats" class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-slate-50 dark:bg-slate-950 px-3 py-2">
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('central.collected') }}</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-100">
                                    {{ new Intl.NumberFormat(undefined, { style: 'currency', currency: dashboard.currency?.code ?? 'USD' }).format((dashboard.payment_stats.total_collected ?? 0) / 100) }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 dark:bg-slate-950 px-3 py-2">
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('central.paid_invoices') }}</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ dashboard.payment_stats.paid_count ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-4">
                            <Link
                                v-if="can('subscriptions.view')"
                                href="/admin/subscriptions"
                                class="inline-flex text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                            >
                                View subscriptions →
                            </Link>
                            <Link
                                v-if="can('payments.view')"
                                href="/admin/payments"
                                class="inline-flex text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                            >
                                View payments →
                            </Link>
                            <Link
                                v-if="can('settings.view')"
                                href="/admin/settings"
                                class="inline-flex text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                            >
                                Manage plans →
                            </Link>
                        </div>
                    </div>

                    <div v-if="dashboard.platform_user_count != null" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.platform_team') }}</h2>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ dashboard.platform_user_count }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $t('central.admin_accounts') }}</p>
                        <Link
                            v-if="can('users.view')"
                            href="/admin/users"
                            class="mt-4 inline-flex text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                        >
                            Manage users →
                        </Link>
                    </div>

                    <div v-if="can('roles.view')" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.access_control') }}</h2>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $t('central.configure_roles_and_permissions_for_the_central_admin_team') }}</p>
                        <Link href="/admin/roles" class="mt-4 inline-flex text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                            Manage roles →
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
