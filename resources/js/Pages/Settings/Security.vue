<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { computed } from 'vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';

const props = defineProps({
    observability: Object,
});

const { activeSection } = useSettingsSection({
    defaultSection: 'overview',
    sections: ['overview', 'policy', 'audit'],
});

const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const form = useForm({
    mfa_required_for_agents: props.observability.settings.mfa_required_for_agents,
    audit_retention_days: props.observability.settings.audit_retention_days,
    closed_ticket_retention_days: props.observability.settings.closed_ticket_retention_days ?? '',
});

const save = () => {
    form.transform((data) => ({
        ...data,
        closed_ticket_retention_days: data.closed_ticket_retention_days === '' ? null : Number(data.closed_ticket_retention_days),
    })).put('/settings/security', { preserveScroll: true });
};

const purgeRetention = () => {
    askConfirm({
        title: 'Run retention purge',
        message: 'This permanently deletes old audit logs and closed tickets. Continue?',
        confirmLabel: 'Run purge',
        variant: 'danger',
        action: () => router.post('/settings/security/purge', {}, { preserveScroll: true }),
    });
};

const adoptionPercent = computed(() => {
    const total = props.observability.mfa_adoption.total;
    if (! total) {
        return 0;
    }
    return Math.round((props.observability.mfa_adoption.enabled / total) * 100);
});
</script>

<template>
    <SettingsLayout
        title="Security & observability"
        description="MFA policy, data retention, and audit trail."
    >
        <div v-show="activeSection === 'overview'" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-sm text-slate-500">MFA adoption</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ adoptionPercent }}%</p>
                            <p class="text-xs text-slate-500">{{ observability.mfa_adoption.enabled }} of {{ observability.mfa_adoption.total }} agents</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-sm text-slate-500">Audit retention</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ observability.settings.audit_retention_days }} days</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-sm text-slate-500">Closed ticket retention</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900">
                                {{ observability.settings.closed_ticket_retention_days ?? 'Disabled' }}
                            </p>
                        </div>
                    </div>

                    <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-medium text-slate-900">7-day event summary</h2>
                        <ul class="mt-4 space-y-2">
                            <li
                                v-for="(total, event) in observability.audit_summary"
                                :key="event"
                                class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-sm"
                            >
                                <span class="text-slate-700">{{ event }}</span>
                                <span class="font-medium text-slate-900">{{ total }}</span>
                            </li>
                            <li v-if="!Object.keys(observability.audit_summary).length" class="text-sm text-slate-500">No audit events yet.</li>
                        </ul>
                    </div>
                </div>

        <div v-show="activeSection === 'policy'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-medium text-slate-900">Security policy</h2>
                    <form class="mt-4 space-y-4" @submit.prevent="save">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.mfa_required_for_agents" type="checkbox" class="rounded border-slate-300" />
                            Require two-factor authentication for all agents
                        </label>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Audit log retention (days)</label>
                            <input v-model.number="form.audit_retention_days" type="number" min="7" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Closed ticket retention (days)</label>
                            <input v-model="form.closed_ticket_retention_days" type="number" min="30" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Leave blank to disable" />
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">
                                Save settings
                            </button>
                            <button type="button" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50" @click="purgeRetention">
                                Run retention purge
                            </button>
                        </div>
                    </form>
                </div>

        <div v-show="activeSection === 'audit'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-medium text-slate-900">Audit trail</h2>
                            <p class="mt-1 text-sm text-slate-600">Full activity history is available on the audit logs page.</p>
                        </div>
                        <Link href="/settings/audit-logs" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            View audit logs
                        </Link>
                    </div>
                </div>

        <AppConfirmDialog
            :open="confirm.open"
            :title="confirm.title"
            :message="confirm.message"
            :confirm-label="confirm.confirmLabel"
            :variant="confirm.variant"
            @close="closeConfirm"
            @confirm="onConfirm"
        />
    </SettingsLayout>
</template>
