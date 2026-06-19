<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    rules: Array,
    meta: Object,
    statuses: Array,
    priorities: Array,
    agents: Array,
    channels: Array,
    webhooks: Array,
    tags: Array,
});

const { t } = useI18n();

const showForm = ref(false);
const editingRule = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const blankRule = () => ({
    name: '',
    trigger: props.meta.triggers[0]?.value ?? 'ticket.created',
    conditions: [],
    actions: [{ type: 'set_priority', value: '' }],
    is_active: true,
    sort_order: 0,
});

const form = useForm(blankRule());

const triggerLabel = (value) => props.meta.triggers.find((item) => item.value === value)?.label ?? value;

const actionLabel = (type) => props.meta.action_types.find((item) => item.value === type)?.label ?? type;

const actionSummary = (action) => {
    if (action.type === 'delay') {
        return `Wait ${action.minutes || action.value || 1} minute(s)`;
    }

    if (action.type === 'add_tag') {
        return `${actionLabel(action.type)} → ${action.value}`;
    }

    if (action.type === 'send_webhook') {
        const webhook = props.webhooks.find((item) => item.id === Number(action.value));

        return `${actionLabel(action.type)} → ${webhook?.name || action.value}`;
    }

    return `${actionLabel(action.type)}${action.value ? ` → ${action.value}` : ''}`;
};

const closeForm = () => {
    showForm.value = false;
};

const openCreate = () => {
    editingRule.value = null;
    form.defaults(blankRule());
    form.reset();
    showForm.value = true;
};

const openEdit = (rule) => {
    editingRule.value = rule;
    form.defaults({
        name: rule.name,
        trigger: rule.trigger,
        conditions: rule.conditions ? [...rule.conditions] : [],
        actions: rule.actions ? [...rule.actions] : [{ type: 'set_priority', value: '' }],
        is_active: rule.is_active,
        sort_order: rule.sort_order ?? 0,
    });
    form.reset();
    showForm.value = true;
};

const addCondition = () => {
    form.conditions.push({ field: 'subject', operator: 'contains', value: '' });
};

const removeCondition = (index) => {
    form.conditions.splice(index, 1);
};

const addAction = () => {
    form.actions.push({ type: 'set_priority', value: '' });
};

const removeAction = (index) => {
    form.actions.splice(index, 1);
};

const save = () => {
    if (editingRule.value) {
        form.put(`/settings/automation/${editingRule.value.id}`, {
            onSuccess: closeForm,
        });
    } else {
        form.post('/settings/automation', {
            onSuccess: () => {
                form.reset();
                closeForm();
            },
        });
    }
};

const destroyRule = (rule) => {
    askConfirm({
        title: t('settings_automation.delete_rule'),
        message: `Delete "${rule.name}"? This cannot be undone.`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/automation/${rule.id}`, { preserveScroll: true }),
    });
};

const valueOptions = computed(() => ({
    ticket_status_id: props.statuses,
    ticket_priority_id: props.priorities,
    channel_id: props.channels,
    assigned_to: props.agents,
}));

const needsValue = (condition) => !['is_empty', 'is_not_empty'].includes(condition.operator);
</script>

<template>
    <SettingsPage :title="$t('settings.automation_rules')" :description="$t('settings.descriptions.automation_rules')" info-section="automation_rules">
        <PlanFeatureBanner feature="automation" />

        <template #actions>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreate">{{ $t('settings_automation.new_rule') }}</button>
        </template>

        <div class="space-y-4">
            <div v-for="rule in rules" :key="rule.id" class="agent-card transition hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold agent-text">{{ rule.name }}</h2>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="rule.is_active ? 'bg-emerald-100 text-emerald-800 dark:text-emerald-200' : 'bg-slate-100 dark:bg-slate-900 agent-text-muted'">
                                {{ rule.is_active ? 'Active' : 'Paused' }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm agent-text-subtle">When {{ triggerLabel(rule.trigger).toLowerCase() }}</p>
                    </div>
                    <div class="flex gap-2">
                        <AppRowActions>
                            <AppEditAction :label="$t('settings_automation.edit')" @click="openEdit(rule)" />
                            <AppDeleteAction :label="$t('settings_automation.delete')" @click="destroyRule(rule)" />
                        </AppRowActions>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium uppercase agent-text-subtle">{{ $t('settings_automation.conditions') }}</p>
                        <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                            <li v-for="(condition, index) in rule.conditions" :key="index">
                                {{ condition.field }} {{ condition.operator }} {{ condition.value ?? '—' }}
                            </li>
                            <li v-if="!rule.conditions?.length" class="agent-text-subtle">{{ $t('settings_automation.always_run') }}</li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase agent-text-subtle">{{ $t('settings_automation.actions') }}</p>
                        <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                            <li v-for="(action, index) in rule.actions" :key="index">
                                {{ actionSummary(action) }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div v-if="!rules.length" class="rounded-xl border border-dashed agent-border bg-white dark:bg-slate-900 px-6 py-12 text-center text-sm agent-text-subtle">
                No automation rules yet.
            </div>
        </div>

        <AppModal
            :open="showForm"
            :title="editingRule ? 'Edit rule' : 'New rule'"
            :description="$t('settings_automation.define_when_this_rule_runs_and_what_it_should_do')"
            variant="drawer"
            @close="closeForm"
        >
            <form id="automation-form" class="space-y-5" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.name') }}</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_automation.trigger') }}</label>
                        <select v-model="form.trigger" class="w-full rounded-lg border agent-border px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                            <option v-for="trigger in meta.triggers" :key="trigger.value" :value="trigger.value">{{ trigger.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_automation.sort_order') }}</label>
                        <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-lg border agent-border px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                    </div>
                </div>

                <AppToggle v-model="form.is_active" :label="$t('common.active')" :description="$t('settings_automation.paused_rules_are_skipped_when_events_fire')" />

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_automation.conditions') }}</p>
                            <p class="text-xs agent-text-subtle">{{ $t('settings_automation.leave_empty_to_run_on_every_matching_trigger') }}</p>
                        </div>
                        <button type="button" class="rounded-lg border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 px-3 py-1.5 text-sm font-medium text-blue-700 dark:text-blue-300 transition hover:bg-blue-100" @click="addCondition">{{ $t('settings_automation.add_condition') }}</button>
                    </div>

                    <p v-if="!form.conditions.length" class="rounded-lg border border-dashed agent-border agent-panel-muted px-4 py-6 text-center text-sm agent-text-subtle">
                        {{ $t('settings_automation.no_conditions_rule_runs_for_all_matching_tickets') }}
                    </p>

                    <TransitionGroup name="list" tag="div" class="space-y-2">
                        <div v-for="(condition, index) in form.conditions" :key="`condition-${index}`" class="rounded-lg border agent-border agent-panel-muted/50 p-3">
                            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]">
                                <select v-model="condition.field" class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm">
                                    <option v-for="field in meta.condition_fields" :key="field.value" :value="field.value">{{ field.label }}</option>
                                </select>
                                <select v-model="condition.operator" class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm">
                                    <option value="equals">{{ $t('settings_automation.equals') }}</option>
                                    <option value="not_equals">{{ $t('settings_automation.not_equals') }}</option>
                                    <option value="contains">{{ $t('settings_automation.contains') }}</option>
                                    <option value="is_empty">{{ $t('settings_automation.is_empty') }}</option>
                                    <option value="is_not_empty">{{ $t('settings_automation.is_not_empty') }}</option>
                                </select>
                                <select
                                    v-if="valueOptions[condition.field]"
                                    v-model="condition.value"
                                    class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm"
                                    :disabled="!needsValue(condition)"
                                >
                                    <option value="">{{ $t('settings_automation.select') }}</option>
                                    <option v-for="option in valueOptions[condition.field]" :key="option.id" :value="option.id">
                                        {{ option.name }}
                                    </option>
                                </select>
                                <input
                                    v-else
                                    v-model="condition.value"
                                    type="text"
                                    class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm"
                                    :disabled="!needsValue(condition)"
                                />
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-slate-400 dark:text-slate-500 transition hover:bg-red-50 dark:bg-red-950/40 hover:text-red-600"
                                    :aria-label="$t('settings_automation.remove_condition')"
                                    @click="removeCondition(index)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_automation.actions') }}</p>
                        <button type="button" class="rounded-lg border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 px-3 py-1.5 text-sm font-medium text-blue-700 dark:text-blue-300 transition hover:bg-blue-100" @click="addAction">{{ $t('settings_automation.add_action') }}</button>
                    </div>

                    <TransitionGroup name="list" tag="div" class="space-y-2">
                        <div v-for="(action, index) in form.actions" :key="`action-${index}`" class="rounded-lg border agent-border agent-panel-muted/50 p-3">
                            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                                <select v-model="action.type" class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm">
                                    <option v-for="actionType in meta.action_types" :key="actionType.value" :value="actionType.value">{{ actionType.label }}</option>
                                </select>
                                <select
                                    v-if="action.type === 'set_status'"
                                    v-model="action.value"
                                    class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm"
                                >
                                    <option value="">{{ $t('settings_automation.select_status') }}</option>
                                    <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                </select>
                                <select
                                    v-else-if="action.type === 'set_priority'"
                                    v-model="action.value"
                                    class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm"
                                >
                                    <option value="">{{ $t('settings_automation.select_priority') }}</option>
                                    <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                                </select>
                                <select
                                    v-else-if="action.type === 'assign_to' || action.type === 'add_watcher'"
                                    v-model="action.value"
                                    class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm"
                                >
                                    <option value="">{{ $t('settings_automation.select_agent') }}</option>
                                    <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                                </select>
                                <select
                                    v-else-if="action.type === 'send_webhook'"
                                    v-model="action.value"
                                    class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm"
                                >
                                    <option value="">{{ $t('settings_automation.select_webhook') }}</option>
                                    <option v-for="webhook in webhooks" :key="webhook.id" :value="webhook.id">{{ webhook.name }}</option>
                                </select>
                                <input
                                    v-else-if="action.type === 'add_tag'"
                                    v-model="action.value"
                                    type="text"
                                    list="automation-tag-suggestions"
                                    class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm"
                                    :placeholder="$t('settings_automation.tag_name')"
                                />
                                <input
                                    v-else-if="action.type === 'delay'"
                                    v-model.number="action.minutes"
                                    type="number"
                                    min="1"
                                    class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm"
                                    :placeholder="$t('settings_automation.minutes')"
                                />
                                <input
                                    v-else
                                    v-model="action.value"
                                    type="text"
                                    class="rounded-lg border agent-border agent-panel px-2 py-2 text-sm"
                                    :placeholder="$t('settings_automation.note_text')"
                                />
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-slate-400 dark:text-slate-500 transition hover:bg-red-50 dark:bg-red-950/40 hover:text-red-600"
                                    :aria-label="$t('settings_automation.remove_action')"
                                    :disabled="form.actions.length === 1"
                                    @click="removeAction(index)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="closeForm">{{ $t('common.cancel') }}</button>
                    <button type="submit" form="automation-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="form.processing">{{ $t('settings_automation.save_rule') }}</button>
                </div>
            </template>
        </AppModal>

        <datalist id="automation-tag-suggestions">
            <option v-for="tag in tags" :key="tag.id" :value="tag.name" />
        </datalist>

        <AppConfirmDialog
            :open="confirm.open"
            :title="confirm.title"
            :message="confirm.message"
            :confirm-label="confirm.confirmLabel"
            :variant="confirm.variant"
            @close="closeConfirm"
            @confirm="onConfirm"
        />
    </SettingsPage>
</template>
