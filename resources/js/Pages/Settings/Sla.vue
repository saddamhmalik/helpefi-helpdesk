<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    policies: Array,
    breachedCount: Number,
    escalationRules: Array,
    escalationMeta: Object,
    slaMeta: Object,
    teams: Array,
    businessHours: Object,
    timezoneOptions: Array,
    weekdays: Array,
});

const { t } = useI18n();

const buildScheduleState = (schedule = {}) => {
    const result = {};

    (props.weekdays ?? []).forEach(({ key }) => {
        const window = schedule?.[key];

        result[key] = window
            ? { enabled: true, start: window.start, end: window.end }
            : { enabled: false, start: '09:00', end: '17:00' };
    });

    return result;
};

const businessHoursForm = useForm({
    name: props.businessHours?.name ?? '',
    timezone: props.businessHours?.timezone ?? 'UTC',
    schedule: buildScheduleState(props.businessHours?.schedule),
});

const showPolicyForm = ref(false);
const policyForm = useForm({
    name: '',
    team_id: '',
    customer_tier: '',
});

const showEscalationForm = ref(false);
const editingRule = ref(null);

const blankRule = () => ({
    sla_policy_id: props.policies[0]?.id ?? '',
    level: 1,
    breach_type: 'first_response',
    delay_minutes_after_breach: 0,
    is_active: true,
    actions: [{ type: 'notify_team_lead', value: 'SLA breached — escalation triggered.' }],
});

const escalationForm = useForm(blankRule());

const breachLabel = (value) => props.escalationMeta.breach_types.find((item) => item.value === value)?.label ?? value;
const levelLabel = (value) => props.escalationMeta.levels.find((item) => item.value === value)?.label ?? `Level ${value}`;
const actionLabel = (value) => props.escalationMeta.action_types.find((item) => item.value === value)?.label ?? value;

const rulesByPolicy = computed(() => {
    const grouped = {};

    props.escalationRules.forEach((rule) => {
        const key = rule.sla_policy_id;
        grouped[key] = grouped[key] ?? [];
        grouped[key].push(rule);
    });

    return grouped;
});

const saveTarget = (target) => {
    router.put(`/settings/sla/targets/${target.id}`, {
        first_response_minutes: target.first_response_minutes,
        resolution_minutes: target.resolution_minutes,
    }, { preserveScroll: true });
};

const openCreateEscalation = (policyId = null) => {
    editingRule.value = null;
    escalationForm.defaults({ ...blankRule(), sla_policy_id: policyId ?? props.policies[0]?.id ?? '' });
    escalationForm.reset();
    showEscalationForm.value = true;
};

const openEditEscalation = (rule) => {
    editingRule.value = rule;
    escalationForm.defaults({
        sla_policy_id: rule.sla_policy_id,
        level: rule.level,
        breach_type: rule.breach_type,
        delay_minutes_after_breach: rule.delay_minutes_after_breach,
        is_active: rule.is_active,
        actions: rule.actions ? JSON.parse(JSON.stringify(rule.actions)) : [],
    });
    escalationForm.reset();
    showEscalationForm.value = true;
};

const addAction = () => {
    escalationForm.actions.push({ type: 'notify_team_lead', value: '' });
};

const removeAction = (index) => {
    escalationForm.actions.splice(index, 1);
};

const saveEscalation = () => {
    escalationForm.post('/settings/sla/escalations', {
        preserveScroll: true,
        onSuccess: () => { showEscalationForm.value = false; },
    });
};

const destroyEscalation = (rule) => {
    router.delete(`/settings/sla/escalations/${rule.id}`, { preserveScroll: true });
};

const policyScopeLabel = (policy) => {
    if (policy.is_default) {
        return 'Default';
    }

    if (policy.team_id) {
        return `Team: ${props.teams.find((team) => team.id === policy.team_id)?.name ?? policy.team_id}`;
    }

    if (policy.customer_tier) {
        const tier = props.slaMeta?.customer_tiers?.find((item) => item.value === policy.customer_tier);
        return `Tier: ${tier?.label ?? policy.customer_tier}`;
    }

    return 'Global';
};

const savePolicy = () => {
    policyForm.post('/settings/sla/policies', {
        preserveScroll: true,
        onSuccess: () => {
            showPolicyForm.value = false;
            policyForm.reset();
        },
    });
};

const deletePolicy = (policy) => {
    router.delete(`/settings/sla/policies/${policy.id}`, { preserveScroll: true });
};

const saveBusinessHours = () => {
    if (!props.businessHours?.id) {
        return;
    }

    const schedule = {};

    (props.weekdays ?? []).forEach(({ key }) => {
        const day = businessHoursForm.schedule[key];
        schedule[key] = day?.enabled ? { start: day.start, end: day.end } : null;
    });

    router.put(`/settings/sla/business-hours/${props.businessHours.id}`, {
        name: businessHoursForm.name,
        timezone: businessHoursForm.timezone,
        schedule,
    }, {
        preserveScroll: true,
        onFinish: () => businessHoursForm.clearErrors(),
    });
};
</script>

<template>
    <SettingsPage :title="`SLA & business hours`" :description="`${breachedCount} ticket(s) currently breached.`">
        <PlanFeatureBanner feature="sla" />

        <template #actions>
            <button type="button" class="agent-btn-secondary" @click="showPolicyForm = true">{{ $t('settings_sla.add_group_policy') }}</button>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreateEscalation()">{{ $t('settings_sla.add_escalation_rule') }}</button>
        </template>

        <section v-if="businessHours" class="mb-6 agent-card">
            <div class="mb-4">
                <h2 class="text-lg font-semibold agent-text">{{ $t('settings_sla.business_hours_timezone') }}</h2>
                <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_sla.controls_sla_timers_live_chat_availability_and_when_agents_are_conside') }}</p>
            </div>

            <form class="space-y-4" @submit.prevent="saveBusinessHours">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_sla.schedule_name') }}</label>
                        <input v-model="businessHoursForm.name" type="text" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.timezone') }}</label>
                        <select v-model="businessHoursForm.timezone" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                            <optgroup v-for="group in timezoneOptions" :key="group.region" :label="group.region">
                                <option v-for="option in group.options" :key="option.value" :value="option.value">
                                    {{ option.label }} ({{ option.value }})
                                </option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border agent-border">
                    <div class="grid grid-cols-[1fr_auto_auto_auto] gap-3 border-b agent-border agent-panel-muted px-4 py-2 text-xs font-semibold uppercase tracking-wide agent-text-subtle">
                        <span>{{ $t('settings_sla.day') }}</span>
                        <span class="text-center">{{ $t('settings_sla.open') }}</span>
                        <span>{{ $t('settings_sla.start') }}</span>
                        <span>{{ $t('settings_sla.end') }}</span>
                    </div>
                    <div
                        v-for="day in weekdays"
                        :key="day.key"
                        class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-b agent-border-subtle px-4 py-3 last:border-b-0"
                    >
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ day.label }}</span>
                        <div class="flex justify-center">
                            <input
                                v-model="businessHoursForm.schedule[day.key].enabled"
                                type="checkbox"
                                class="rounded agent-border"
                            />
                        </div>
                        <input
                            v-model="businessHoursForm.schedule[day.key].start"
                            type="time"
                            :disabled="!businessHoursForm.schedule[day.key].enabled"
                            class="rounded-lg border agent-border px-2 py-1.5 text-sm disabled:agent-panel-muted disabled:text-slate-400 dark:text-slate-500"
                        />
                        <input
                            v-model="businessHoursForm.schedule[day.key].end"
                            type="time"
                            :disabled="!businessHoursForm.schedule[day.key].enabled"
                            class="rounded-lg border agent-border px-2 py-1.5 text-sm disabled:agent-panel-muted disabled:text-slate-400 dark:text-slate-500"
                        />
                    </div>
                </div>

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="businessHoursForm.processing">{{ $t('settings_sla.save_business_hours') }}</button>
            </form>
        </section>

        <div v-if="showPolicyForm" class="mb-6 agent-card">
            <h2 class="text-lg font-semibold agent-text">{{ $t('settings_sla.new_group_sla_policy') }}</h2>
            <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_sla.targets_are_copied_from_the_default_policy_team_policies_take_preceden') }}</p>
            <form class="mt-4 grid gap-4 md:grid-cols-3" @submit.prevent="savePolicy">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.name') }}</label>
                    <input v-model="policyForm.name" type="text" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings.groups.team') }}</label>
                    <select v-model="policyForm.team_id" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                        <option value="">{{ $t('settings_sla.none') }}</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_sla.customer_tier') }}</label>
                    <select v-model="policyForm.customer_tier" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                        <option value="">{{ $t('settings_sla.none') }}</option>
                        <option v-for="tier in slaMeta?.customer_tiers ?? []" :key="tier.value" :value="tier.value">{{ tier.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-3 flex gap-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="policyForm.processing">{{ $t('settings_sla.create_policy') }}</button>
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm text-slate-700 dark:text-slate-300" @click="showPolicyForm = false">{{ $t('common.cancel') }}</button>
                </div>
            </form>
        </div>

        <div v-for="policy in policies" :key="policy.id" class="mb-6 agent-card">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold agent-text">{{ policy.name }}</h2>
                    <p class="text-sm agent-text-subtle">{{ policy.business_hours?.name }} · {{ policy.business_hours?.timezone }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-slate-100 dark:bg-slate-900 px-3 py-1 text-xs font-medium text-slate-700 dark:text-slate-300">{{ policyScopeLabel(policy) }}</span>
                    <span v-if="policy.is_default" class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800">{{ $t('settings_sla.default') }}</span>
                    <AppDeleteAction
                        v-if="!policy.is_default"
                        :label="$t('settings_sla.delete')"
                        @click="deletePolicy(policy)"
                    />
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <div v-for="target in policy.targets" :key="target.id" class="rounded-lg border agent-border-subtle p-4">
                    <p class="mb-3 font-medium agent-text">{{ target.priority?.name }} priority</p>
                    <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="saveTarget(target)">
                        <div>
                            <label class="mb-1 block text-xs font-medium agent-text-muted">{{ $t('settings_sla.first_response_min') }}</label>
                            <input v-model.number="target.first_response_minutes" type="number" min="1" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium agent-text-muted">{{ $t('settings_sla.resolution_min') }}</label>
                            <input v-model.number="target.resolution_minutes" type="number" min="1" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="rounded-lg border agent-border px-3 py-2 text-sm text-slate-700 dark:text-slate-300 agent-hover-surface">{{ $t('common.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-6 border-t agent-border-subtle pt-6">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('settings_sla.violation_escalation_criteria') }}</h3>
                    <button type="button" class="text-sm text-blue-600" @click="openCreateEscalation(policy.id)">{{ $t('settings_sla.add_rule') }}</button>
                </div>
                <div v-if="rulesByPolicy[policy.id]?.length" class="space-y-3">
                    <div v-for="rule in rulesByPolicy[policy.id]" :key="rule.id" class="rounded-lg border agent-border-subtle agent-panel-muted/70 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium agent-text">{{ levelLabel(rule.level) }} · {{ breachLabel(rule.breach_type) }}</p>
                                <p class="mt-1 text-xs agent-text-subtle">Runs {{ rule.delay_minutes_after_breach }} minute(s) after breach</p>
                                <ul class="mt-3 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                                    <li v-for="(action, index) in rule.actions" :key="index">
                                        {{ actionLabel(action.type) }}<span v-if="action.value"> — {{ action.value }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="flex gap-2">
                                <AppRowActions>
                                    <AppEditAction :label="$t('settings_sla.edit')" @click="openEditEscalation(rule)" />
                                    <AppDeleteAction :label="$t('settings_sla.delete')" @click="destroyEscalation(rule)" />
                                </AppRowActions>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm agent-text-subtle">{{ $t('settings_sla.no_escalation_rules_configured_for_this_policy') }}</p>
            </div>
        </div>

        <AppModal :open="showEscalationForm" :title="$t('settings_sla.escalation_rule')" variant="drawer" @close="showEscalationForm = false">
            <form id="escalation-form" class="space-y-4" @submit.prevent="saveEscalation">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_sla.policy') }}</label>
                        <select v-model="escalationForm.sla_policy_id" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                            <option v-for="policy in policies" :key="policy.id" :value="policy.id">{{ policy.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_sla.level') }}</label>
                        <select v-model.number="escalationForm.level" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                            <option v-for="level in escalationMeta.levels" :key="level.value" :value="level.value">{{ level.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_sla.violation_type') }}</label>
                        <select v-model="escalationForm.breach_type" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                            <option v-for="type in escalationMeta.breach_types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_sla.delay_after_breach_min') }}</label>
                        <input v-model.number="escalationForm.delay_minutes_after_breach" type="number" min="0" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_sla.actions') }}</p>
                        <button type="button" class="text-sm text-blue-600" @click="addAction">{{ $t('settings_sla.add_action') }}</button>
                    </div>
                    <div v-for="(action, index) in escalationForm.actions" :key="index" class="mb-2 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <select v-model="action.type" class="rounded-lg border agent-border px-2 py-2 text-sm">
                            <option v-for="type in escalationMeta.action_types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <input v-model="action.value" type="text" :placeholder="$t('settings_sla.note_or_value')" class="rounded-lg border agent-border px-2 py-2 text-sm" />
                        <button type="button" class="text-sm text-red-600" @click="removeAction(index)">{{ $t('settings_sla.remove') }}</button>
                    </div>
                </div>

                <AppToggle v-model="escalationForm.is_active" :label="$t('common.active')" />
            </form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm" @click="showEscalationForm = false">{{ $t('common.cancel') }}</button>
                    <button type="submit" form="escalation-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="escalationForm.processing">{{ $t('settings_sla.save_rule') }}</button>
                </div>
            </template>
        </AppModal>
    </SettingsPage>
</template>
