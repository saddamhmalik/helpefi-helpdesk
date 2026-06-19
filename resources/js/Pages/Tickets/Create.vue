<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import CcEmailField from '../../Components/CcEmailField.vue';
import CustomFields from '../../Components/CustomFields.vue';
import FormRichTextField from '../../Components/FormRichTextField.vue';
import FormField from '../../Components/FormField.vue';
import FormPage from '../../Components/FormPage.vue';
import FormSection from '../../Components/FormSection.vue';
import PlanLimitBanner from '../../Components/PlanLimitBanner.vue';
import RequesterField from '../../Components/RequesterField.vue';
import { usePlanLimit } from '../../composables/usePlanFeature.js';
import { formInputClass, formSelectClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    statuses: Array,
    priorities: Array,
    agents: Array,
    departments: Array,
    teams: Array,
    customFieldDefinitions: { type: Array, default: () => [] },
    ticketTypes: { type: Array, default: () => [] },
    defaultType: { type: String, default: null },
});

const { t } = useI18n();
const { atLimit: ticketLimitReached } = usePlanLimit('tickets_monthly');

const form = useForm({
    subject: '',
    description: '',
    contact_id: '',
    requester_email: '',
    requester_name: '',
    cc_emails: [],
    assigned_to: '',
    department_id: '',
    team_id: '',
    ticket_status_id: props.statuses.find((s) => s.slug === 'open')?.id ?? props.statuses[0]?.id,
    ticket_priority_id: props.priorities.find((p) => p.slug === 'normal')?.id ?? props.priorities[0]?.id,
    type: props.defaultType
        ?? props.ticketTypes.find((item) => item.value === 'incident')?.value
        ?? props.ticketTypes[0]?.value
        ?? '',
    custom_fields: {},
});

const filteredTeams = computed(() =>
    props.teams.filter((team) => !form.department_id || team.department_id === Number(form.department_id)),
);

const hasRequester = () => Boolean(form.contact_id) || Boolean(form.requester_email?.trim());

const submit = () => {
    if (ticketLimitReached.value) {
        return;
    }

    if (!hasRequester()) {
        form.setError('requester_email', t('tickets.requester_required'));

        return;
    }

    form.post('/tickets');
};
</script>

<template>
    <Head :title="$t('tickets.new_ticket')" />
    <AgentLayout>
        <div class="mx-auto max-w-3xl px-4 pt-6">
            <PlanLimitBanner limit-key="tickets_monthly" />
        </div>
        <FormPage
            :description="$t('tickets.capture_the_request_details_assign_ownership_and_set_the_initial_workf')"
            cancel-href="/tickets"
            :submit-label="$t('tickets.create_ticket')"
            :processing="form.processing"
            :submit-disabled="ticketLimitReached"
            max-width="md"
            @submit="submit"
        >
            <FormSection :title="$t('tickets.ticket_details')">
                <FormField :label="$t('tickets.subject')" required :error="form.errors.subject">
                    <input v-model="form.subject" type="text" :class="formInputClass" :placeholder="$t('tickets.brief_summary_of_the_issue')" required />
                </FormField>
                <FormRichTextField
                    v-model="form.description"
                    :label="$t('tickets.description')"
                    :error="form.errors.description"
                    :placeholder="$t('tickets.include_context_steps_to_reproduce_or_customer_message')"
                />
            </FormSection>

            <FormSection :title="$t('tickets.people_routing')" :description="$t('tickets.set_the_requester_cc_recipients_and_route_the_ticket_to_the_right_team')">
                <div class="space-y-5">
                    <FormField
                        :label="$t('tickets.requester')"
                        required
                        :error="form.errors.contact_id || form.errors.requester_email"
                    >
                        <RequesterField
                            v-model:contact-id="form.contact_id"
                            v-model:requester-email="form.requester_email"
                            v-model:requester-name="form.requester_name"
                            :error="form.errors.contact_id || form.errors.requester_email"
                        />
                    </FormField>
                    <FormField :label="$t('tickets.cc')" :error="form.errors.cc_emails">
                        <CcEmailField v-model="form.cc_emails" :error="form.errors.cc_emails" />
                    </FormField>
                </div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <FormField :label="$t('tickets.assignee')" :error="form.errors.assigned_to">
                        <select v-model="form.assigned_to" :class="formSelectClass">
                            <option value="">{{ $t('tickets.unassigned') }}</option>
                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                        </select>
                    </FormField>
                    <FormField :label="$t('tickets.department')" :error="form.errors.department_id">
                        <select v-model="form.department_id" :class="formSelectClass">
                            <option value="">{{ $t('tickets.no_department') }}</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                    </FormField>
                    <FormField :label="$t('tickets.team')" :error="form.errors.team_id">
                        <select v-model="form.team_id" :class="formSelectClass">
                            <option value="">{{ $t('tickets.no_team') }}</option>
                            <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </FormField>
                </div>
            </FormSection>

            <FormSection :title="$t('tickets.workflow')">
                <FormField
                    v-if="ticketTypes.length"
                    :label="$t('tickets.ticket_type')"
                    :error="form.errors.type"
                >
                    <select v-model="form.type" :class="formSelectClass" required>
                        <option v-for="ticketType in ticketTypes" :key="ticketType.value" :value="ticketType.value">
                            {{ ticketType.singular }}
                        </option>
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ ticketTypes.find((item) => item.value === form.type)?.description }}
                    </p>
                </FormField>
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField :label="$t('tickets.status')" :error="form.errors.ticket_status_id">
                        <select v-model="form.ticket_status_id" :class="formSelectClass">
                            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                        </select>
                    </FormField>
                    <FormField :label="$t('tickets.priority')" :error="form.errors.ticket_priority_id">
                        <select v-model="form.ticket_priority_id" :class="formSelectClass">
                            <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                        </select>
                    </FormField>
                </div>
            </FormSection>

            <CustomFields
                v-model="form.custom_fields"
                :definitions="customFieldDefinitions"
                :errors="form.errors"
            />
        </FormPage>
    </AgentLayout>
</template>
