<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PlatformStatCard from '../../../Components/Platform/PlatformStatCard.vue';
import { usePlatformAdmin } from '../../../composables/usePlatformAdmin.js';

defineProps({
    dashboard: Object,
});

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
    <Head title="Dashboard" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <PageHeader
                title="Dashboard"
                description="Overview of workspaces, billing, and platform configuration."
            />

            <section v-if="quickLinks.length > 1" class="mb-8">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Quick access</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="item in quickLinks.filter((link) => link.href !== '/admin/dashboard')"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:shadow-md"
                    >
                        <p class="font-medium text-slate-900">{{ item.label }}</p>
                        <p class="mt-1 text-sm text-slate-500">Open {{ item.label.toLowerCase() }}</p>
                    </Link>
                </div>
            </section>

            <section v-if="dashboard.workspace_stats" class="mb-8">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Workspaces</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <PlatformStatCard label="Total" :value="dashboard.workspace_stats.total" />
                    <PlatformStatCard label="Active plans" :value="dashboard.workspace_stats.active" tone="emerald" />
                    <PlatformStatCard label="On trial" :value="dashboard.workspace_stats.on_trial" tone="blue" />
                    <PlatformStatCard label="Trial expired" :value="dashboard.workspace_stats.expired_trial" tone="amber" />
                    <PlatformStatCard label="Blocked" :value="dashboard.workspace_stats.blocked" tone="red" />
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-3">
                <section v-if="dashboard.recent_workspaces?.length" class="lg:col-span-2">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-slate-900">Recent workspaces</h2>
                        <Link v-if="can('tenants.view')" href="/admin/tenants" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                            View all
                        </Link>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <ul class="divide-y divide-slate-100">
                            <li v-for="workspace in dashboard.recent_workspaces" :key="workspace.id" class="flex items-start justify-between gap-4 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900">{{ workspace.name }}</p>
                                    <p class="text-sm text-slate-500">{{ workspace.slug }}</p>
                                    <a
                                        v-if="workspace.url"
                                        :href="workspace.url"
                                        target="_blank"
                                        class="mt-1 inline-block text-xs font-medium text-blue-600 hover:text-blue-700"
                                    >
                                        Open workspace
                                    </a>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize"
                                    :class="{
                                        'bg-red-100 text-red-700': statusTone(workspace) === 'red',
                                        'bg-blue-100 text-blue-700': statusTone(workspace) === 'blue',
                                        'bg-amber-100 text-amber-800': statusTone(workspace) === 'amber',
                                        'bg-emerald-100 text-emerald-700': statusTone(workspace) === 'emerald',
                                    }"
                                >
                                    {{ statusLabel(workspace) }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900">Billing</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            Stripe is
                            <span class="font-medium" :class="dashboard.stripe_enabled ? 'text-emerald-700' : 'text-amber-700'">
                                {{ dashboard.stripe_enabled ? 'connected' : 'not configured' }}
                            </span>
                            for tenant checkout.
                        </p>
                        <div v-if="dashboard.payment_stats" class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-xs text-slate-500">Collected</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">
                                    {{ new Intl.NumberFormat(undefined, { style: 'currency', currency: dashboard.currency?.code ?? 'USD' }).format((dashboard.payment_stats.total_collected ?? 0) / 100) }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-xs text-slate-500">Paid invoices</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">{{ dashboard.payment_stats.paid_count ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-4">
                            <Link
                                v-if="can('subscriptions.view')"
                                href="/admin/subscriptions"
                                class="inline-flex text-sm font-medium text-blue-600 hover:text-blue-700"
                            >
                                View subscriptions →
                            </Link>
                            <Link
                                v-if="can('payments.view')"
                                href="/admin/payments"
                                class="inline-flex text-sm font-medium text-blue-600 hover:text-blue-700"
                            >
                                View payments →
                            </Link>
                            <Link
                                v-if="can('settings.view')"
                                href="/admin/settings"
                                class="inline-flex text-sm font-medium text-blue-600 hover:text-blue-700"
                            >
                                Manage plans →
                            </Link>
                        </div>
                    </div>

                    <div v-if="dashboard.platform_user_count != null" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900">Platform team</h2>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-slate-900">{{ dashboard.platform_user_count }}</p>
                        <p class="mt-1 text-sm text-slate-500">admin accounts</p>
                        <Link
                            v-if="can('users.view')"
                            href="/admin/users"
                            class="mt-4 inline-flex text-sm font-medium text-blue-600 hover:text-blue-700"
                        >
                            Manage users →
                        </Link>
                    </div>

                    <div v-if="can('roles.view')" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900">Access control</h2>
                        <p class="mt-2 text-sm text-slate-600">Configure roles and permissions for the central admin team.</p>
                        <Link href="/admin/roles" class="mt-4 inline-flex text-sm font-medium text-blue-600 hover:text-blue-700">
                            Manage roles →
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
