<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';

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
    <SettingsLayout :title="`SLA & business hours`" :description="`${breachedCount} ticket(s) currently breached.`">
        <template #actions>
            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="showPolicyForm = true">Add group policy</button>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreateEscalation()">Add escalation rule</button>
        </template>

        <section v-if="businessHours" class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Business hours & timezone</h2>
                <p class="mt-1 text-sm text-slate-500">Controls SLA timers, live chat availability, and when agents are considered on duty.</p>
            </div>

            <form class="space-y-4" @submit.prevent="saveBusinessHours">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Schedule name</label>
                        <input v-model="businessHoursForm.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Timezone</label>
                        <select v-model="businessHoursForm.timezone" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <optgroup v-for="group in timezoneOptions" :key="group.region" :label="group.region">
                                <option v-for="option in group.options" :key="option.value" :value="option.value">
                                    {{ option.label }} ({{ option.value }})
                                </option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <div class="grid grid-cols-[1fr_auto_auto_auto] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <span>Day</span>
                        <span class="text-center">Open</span>
                        <span>Start</span>
                        <span>End</span>
                    </div>
                    <div
                        v-for="day in weekdays"
                        :key="day.key"
                        class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0"
                    >
                        <span class="text-sm font-medium text-slate-800">{{ day.label }}</span>
                        <div class="flex justify-center">
                            <input
                                v-model="businessHoursForm.schedule[day.key].enabled"
                                type="checkbox"
                                class="rounded border-slate-300"
                            />
                        </div>
                        <input
                            v-model="businessHoursForm.schedule[day.key].start"
                            type="time"
                            :disabled="!businessHoursForm.schedule[day.key].enabled"
                            class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm disabled:bg-slate-50 disabled:text-slate-400"
                        />
                        <input
                            v-model="businessHoursForm.schedule[day.key].end"
                            type="time"
                            :disabled="!businessHoursForm.schedule[day.key].enabled"
                            class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm disabled:bg-slate-50 disabled:text-slate-400"
                        />
                    </div>
                </div>

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="businessHoursForm.processing">
                    Save business hours
                </button>
            </form>
        </section>

        <div v-if="showPolicyForm" class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">New group SLA policy</h2>
            <p class="mt-1 text-sm text-slate-500">Targets are copied from the default policy. Team policies take precedence over customer tier policies.</p>
            <form class="mt-4 grid gap-4 md:grid-cols-3" @submit.prevent="savePolicy">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                    <input v-model="policyForm.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Team</label>
                    <select v-model="policyForm.team_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">None</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Customer tier</label>
                    <select v-model="policyForm.customer_tier" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">None</option>
                        <option v-for="tier in slaMeta?.customer_tiers ?? []" :key="tier.value" :value="tier.value">{{ tier.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-3 flex gap-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="policyForm.processing">Create policy</button>
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700" @click="showPolicyForm = false">Cancel</button>
                </div>
            </form>
        </div>

        <div v-for="policy in policies" :key="policy.id" class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ policy.name }}</h2>
                    <p class="text-sm text-slate-500">{{ policy.business_hours?.name }} · {{ policy.business_hours?.timezone }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ policyScopeLabel(policy) }}</span>
                    <span v-if="policy.is_default" class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800">Default</span>
                    <button v-if="!policy.is_default" type="button" class="text-sm text-red-600" @click="deletePolicy(policy)">Delete</button>
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <div v-for="target in policy.targets" :key="target.id" class="rounded-lg border border-slate-100 p-4">
                    <p class="mb-3 font-medium text-slate-900">{{ target.priority?.name }} priority</p>
                    <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="saveTarget(target)">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">First response (min)</label>
                            <input v-model.number="target.first_response_minutes" type="number" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Resolution (min)</label>
                            <input v-model.number="target.resolution_minutes" type="number" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-6 border-t border-slate-100 pt-6">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Violation escalation criteria</h3>
                    <button type="button" class="text-sm text-blue-600" @click="openCreateEscalation(policy.id)">Add rule</button>
                </div>
                <div v-if="rulesByPolicy[policy.id]?.length" class="space-y-3">
                    <div v-for="rule in rulesByPolicy[policy.id]" :key="rule.id" class="rounded-lg border border-slate-100 bg-slate-50/70 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ levelLabel(rule.level) }} · {{ breachLabel(rule.breach_type) }}</p>
                                <p class="mt-1 text-xs text-slate-500">Runs {{ rule.delay_minutes_after_breach }} minute(s) after breach</p>
                                <ul class="mt-3 space-y-1 text-sm text-slate-700">
                                    <li v-for="(action, index) in rule.actions" :key="index">
                                        {{ actionLabel(action.type) }}<span v-if="action.value"> — {{ action.value }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="text-sm text-blue-600" @click="openEditEscalation(rule)">Edit</button>
                                <button type="button" class="text-sm text-red-600" @click="destroyEscalation(rule)">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-500">No escalation rules configured for this policy.</p>
            </div>
        </div>

        <AppModal :open="showEscalationForm" title="Escalation rule" variant="drawer" @close="showEscalationForm = false">
            <form id="escalation-form" class="space-y-4" @submit.prevent="saveEscalation">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Policy</label>
                        <select v-model="escalationForm.sla_policy_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option v-for="policy in policies" :key="policy.id" :value="policy.id">{{ policy.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Level</label>
                        <select v-model.number="escalationForm.level" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option v-for="level in escalationMeta.levels" :key="level.value" :value="level.value">{{ level.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Violation type</label>
                        <select v-model="escalationForm.breach_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option v-for="type in escalationMeta.breach_types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Delay after breach (min)</label>
                        <input v-model.number="escalationForm.delay_minutes_after_breach" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-700">Actions</p>
                        <button type="button" class="text-sm text-blue-600" @click="addAction">Add action</button>
                    </div>
                    <div v-for="(action, index) in escalationForm.actions" :key="index" class="mb-2 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <select v-model="action.type" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            <option v-for="type in escalationMeta.action_types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <input v-model="action.value" type="text" placeholder="Note or value" class="rounded-lg border border-slate-300 px-2 py-2 text-sm" />
                        <button type="button" class="text-sm text-red-600" @click="removeAction(index)">Remove</button>
                    </div>
                </div>

                <AppToggle v-model="escalationForm.is_active" label="Active" />
            </form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="showEscalationForm = false">Cancel</button>
                    <button type="submit" form="escalation-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="escalationForm.processing">Save rule</button>
                </div>
            </template>
        </AppModal>
    </SettingsLayout>
</template>
