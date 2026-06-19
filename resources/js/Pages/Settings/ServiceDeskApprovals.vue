<script setup>
import { useForm } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import AppToggle from '../../Components/AppToggle.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    change_requires_approval: Boolean,
    change_approver_user_ids: Array,
    agents: Array,
});

const { t } = useI18n();

const form = useForm({
    change_requires_approval: props.change_requires_approval ?? false,
    change_approver_user_ids: props.change_approver_user_ids ?? [],
});

const toggleApprover = (agentId) => {
    const ids = new Set(form.change_approver_user_ids.map(Number));
    const id = Number(agentId);

    if (ids.has(id)) {
        ids.delete(id);
    } else {
        ids.add(id);
    }

    form.change_approver_user_ids = Array.from(ids);
};

const save = () => {
    form.put('/settings/service-desk/approvals', { preserveScroll: true });
};
</script>

<template>
    <SettingsPage
        :title="$t('settings.change_approvals')"
        :description="$t('settings.descriptions.change_approvals')"
        info-section="change_approvals"
    >
        <PlanFeatureBanner feature="service_desk" />

        <form class="max-w-3xl space-y-6 agent-card" @submit.prevent="save">
            <AppToggle v-model="form.change_requires_approval" :label="$t('settings_service_desk_approvals.require_approval_for_change_tickets')" />
            <p class="text-sm agent-text-muted">
                Applies when agents create tickets with type <span class="font-medium">{{ $t('settings_service_desk_approvals.change') }}</span> outside the service catalog.
            </p>

            <div v-if="form.change_requires_approval">
                <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_service_desk_approvals.approvers_in_order') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="agent in agents"
                        :key="agent.id"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="form.change_approver_user_ids.includes(agent.id) ? 'bg-violet-700 text-white' : 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 ring-1 agent-border'"
                        @click="toggleApprover(agent.id)"
                    >
                        {{ agent.name }}
                    </button>
                </div>
                <p v-if="form.errors.change_approver_user_ids" class="mt-2 text-sm text-red-600">{{ form.errors.change_approver_user_ids }}</p>
            </div>

            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" :disabled="form.processing">{{ $t('settings_service_desk_approvals.save_settings') }}</button>
        </form>
    </SettingsPage>
</template>
