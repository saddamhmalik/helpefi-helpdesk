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
import RequesterField from '../../Components/RequesterField.vue';
import { formInputClass, formSelectClass } from '../../composables/useFormControls.js';

const props = defineProps({
    statuses: Array,
    priorities: Array,
    agents: Array,
    departments: Array,
    teams: Array,
    customFieldDefinitions: { type: Array, default: () => [] },
});

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
    custom_fields: {},
});

const filteredTeams = computed(() =>
    props.teams.filter((team) => !form.department_id || team.department_id === Number(form.department_id)),
);

const submit = () => form.post('/tickets');
</script>

<template>
    <Head title="New ticket" />
    <AgentLayout>
        <FormPage
            description="Capture the request details, assign ownership, and set the initial workflow state."
            cancel-href="/tickets"
            submit-label="Create ticket"
            :processing="form.processing"
            max-width="md"
            @submit="submit"
        >
            <FormSection title="Ticket details">
                <FormField label="Subject" required :error="form.errors.subject">
                    <input v-model="form.subject" type="text" :class="formInputClass" placeholder="Brief summary of the issue" required />
                </FormField>
                <FormRichTextField
                    v-model="form.description"
                    label="Description"
                    :error="form.errors.description"
                    placeholder="Include context, steps to reproduce, or customer message"
                />
            </FormSection>

            <FormSection title="People & routing" description="Set the requester, CC recipients, and route the ticket to the right team.">
                <div class="space-y-5">
                    <FormField
                        label="Requester"
                        :error="form.errors.contact_id || form.errors.requester_email"
                    >
                        <RequesterField
                            v-model:contact-id="form.contact_id"
                            v-model:requester-email="form.requester_email"
                            v-model:requester-name="form.requester_name"
                            :error="form.errors.contact_id || form.errors.requester_email"
                        />
                    </FormField>
                    <FormField label="CC" :error="form.errors.cc_emails">
                        <CcEmailField v-model="form.cc_emails" :error="form.errors.cc_emails" />
                    </FormField>
                </div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <FormField label="Assignee" :error="form.errors.assigned_to">
                        <select v-model="form.assigned_to" :class="formSelectClass">
                            <option value="">Unassigned</option>
                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                        </select>
                    </FormField>
                    <FormField label="Department" :error="form.errors.department_id">
                        <select v-model="form.department_id" :class="formSelectClass">
                            <option value="">No department</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                    </FormField>
                    <FormField label="Team" :error="form.errors.team_id">
                        <select v-model="form.team_id" :class="formSelectClass">
                            <option value="">No team</option>
                            <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </FormField>
                </div>
            </FormSection>

            <FormSection title="Workflow">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Status" :error="form.errors.ticket_status_id">
                        <select v-model="form.ticket_status_id" :class="formSelectClass">
                            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                        </select>
                    </FormField>
                    <FormField label="Priority" :error="form.errors.ticket_priority_id">
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
