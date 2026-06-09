<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import CustomFieldEditor from '../../Components/CustomFieldEditor.vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';

const props = defineProps({
    settings: Object,
});

const { activeSection } = useSettingsSection({
    defaultSection: 'general',
    sections: ['general', 'email', 'contact_fields', 'ticket_fields', 'user_fields'],
});

const cloneFields = (fields) => (fields ? JSON.parse(JSON.stringify(fields)) : []);

const form = useForm({
    ticket_number_prefix: props.settings.ticket_number_prefix,
    contact_fields: cloneFields(props.settings.contact_fields),
    ticket_fields: cloneFields(props.settings.ticket_fields),
    user_fields: cloneFields(props.settings.user_fields),
    auto_first_response_enabled: props.settings.auto_first_response_enabled ?? false,
    auto_first_response_body: props.settings.auto_first_response_body ?? '',
    email_blocklist: [...(props.settings.email_blocklist ?? [])],
});

const blocklistText = computed({
    get: () => form.email_blocklist.join('\n'),
    set: (value) => {
        form.email_blocklist = String(value)
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter(Boolean);
    },
});

const addField = (key) => {
    form[key].push({ name: '', label: '', type: 'text', required: false, options: [] });
};

const removeField = (key, index) => {
    form[key].splice(index, 1);
};

const save = () => {
    form.put('/settings/tickets', { preserveScroll: true });
};
</script>

<template>
    <SettingsLayout
        title="Ticket settings"
        description="Configure ticket numbering, inbound email behavior, and custom fields."
    >
        <form @submit.prevent="save">
            <div v-show="activeSection === 'general'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Ticket numbering</h2>
                        <p class="mt-1 text-sm text-slate-500">New tickets use this prefix followed by a sequential number (e.g. HD-00004).</p>

                        <div class="mt-4 sm:max-w-xs">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Number prefix</label>
                            <input
                                v-model="form.ticket_number_prefix"
                                type="text"
                                maxlength="20"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase"
                                placeholder="HD-"
                                required
                            />
                            <p v-if="form.errors.ticket_number_prefix" class="mt-1 text-sm text-red-600">{{ form.errors.ticket_number_prefix }}</p>
                            <p class="mt-1 text-xs text-slate-500">A hyphen is added automatically if omitted. Existing ticket numbers are not changed.</p>
                        </div>
                    </div>

            <div v-show="activeSection === 'email'" class="space-y-6">
                        <div class="max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-900">Automatic first response</h2>
                                    <p class="mt-1 text-sm text-slate-500">Send an acknowledgment email when a new email ticket is created.</p>
                                </div>
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input v-model="form.auto_first_response_enabled" type="checkbox" class="peer sr-only" />
                                    <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-blue-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500/30 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition after:content-[''] peer-checked:after:translate-x-full" />
                                </label>
                            </div>

                            <div class="mt-4">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Message template</label>
                                <textarea
                                    v-model="form.auto_first_response_body"
                                    rows="8"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"
                                    :disabled="!form.auto_first_response_enabled"
                                />
                                <p v-if="form.errors.auto_first_response_body" class="mt-1 text-sm text-red-600">{{ form.errors.auto_first_response_body }}</p>
                                <p class="mt-2 text-xs text-slate-500">
                                    Placeholders:
                                    <code v-pre class="rounded bg-slate-100 px-1">{{ticket_number}}</code>,
                                    <code v-pre class="rounded bg-slate-100 px-1">{{ticket_subject}}</code>,
                                    <code v-pre class="rounded bg-slate-100 px-1">{{contact_name}}</code>,
                                    <code v-pre class="rounded bg-slate-100 px-1">{{contact_email}}</code>
                                </p>
                            </div>
                        </div>

                        <div class="max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 class="text-lg font-semibold text-slate-900">Email blocklist</h2>
                            <p class="mt-1 text-sm text-slate-500">Inbound email from these addresses or domains will be ignored. No ticket is created and replies are not added.</p>

                            <div class="mt-4">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Blocked senders</label>
                                <textarea
                                    v-model="blocklistText"
                                    rows="6"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"
                                    placeholder="noreply@company.com&#10;mailer-daemon@&#10;spam-domain.com"
                                />
                                <p v-if="form.errors.email_blocklist" class="mt-1 text-sm text-red-600">{{ form.errors.email_blocklist }}</p>
                                <p class="mt-2 text-xs text-slate-500">One entry per line. Use a full email address or a domain name (e.g. <code class="rounded bg-slate-100 px-1">example.com</code>).</p>
                            </div>
                        </div>
                    </div>

            <CustomFieldEditor
                v-show="activeSection === 'contact_fields'"
                        :fields="form.contact_fields"
                        title="Customer fields"
                        description="Optional fields shown when creating or editing customers."
                        @add="addField('contact_fields')"
                        @remove="(index) => removeField('contact_fields', index)"
            />

            <CustomFieldEditor
                v-show="activeSection === 'ticket_fields'"
                        :fields="form.ticket_fields"
                        title="Ticket fields"
                        description="Optional fields shown when creating or editing tickets."
                        @add="addField('ticket_fields')"
                        @remove="(index) => removeField('ticket_fields', index)"
            />

            <CustomFieldEditor
                v-show="activeSection === 'user_fields'"
                        :fields="form.user_fields"
                        title="Team member fields"
                        description="Optional fields shown when adding or editing team members."
                        @add="addField('user_fields')"
                        @remove="(index) => removeField('user_fields', index)"
            />

            <div class="mt-6 flex items-center justify-end border-t border-slate-200 pt-4">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">
                    Save settings
                </button>
            </div>
        </form>
    </SettingsLayout>
</template>
