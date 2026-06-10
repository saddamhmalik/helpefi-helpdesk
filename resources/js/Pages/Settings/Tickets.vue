<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import SettingsSectionNav from '../../Components/SettingsSectionNav.vue';
import CustomFieldEditor from '../../Components/CustomFieldEditor.vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    settings: Object,
});

const { t } = useI18n();

const { activeSection } = useSettingsSection({
    defaultSection: 'general',
    sections: ['general', 'email', 'contact_fields', 'ticket_fields', 'user_fields'],
});

const pageTitles = {
    general: {
        title: t('settings.ticket_numbering'),
        description: t('settings_tickets.set_the_prefix_used_for_new_ticket_numbers_e_g_hd-00004'),
    },
    email: {
        title: t('settings.email_auto_reply'),
        description: t('settings_tickets.automatic_first_response_and_inbound_email_blocklist'),
    },
    contact_fields: {
        title: t('settings.customer_fields'),
        description: t('settings_tickets.optional_fields_shown_when_creating_or_editing_customers'),
    },
    ticket_fields: {
        title: t('settings.ticket_fields'),
        description: t('settings_tickets.optional_fields_shown_when_creating_or_editing_tickets'),
    },
    user_fields: {
        title: t('settings.agent_fields'),
        description: t('settings_tickets.optional_fields_shown_when_adding_or_editing_team_members'),
    },
};

const pageMeta = computed(() => pageTitles[activeSection.value] ?? pageTitles.general);

const sectionTabs = computed(() => [
    { id: 'general', label: t('settings.ticket_numbering') },
    { id: 'email', label: t('settings.email_auto_reply') },
    { id: 'contact_fields', label: t('settings.customer_fields') },
    { id: 'ticket_fields', label: t('settings.ticket_fields') },
    { id: 'user_fields', label: t('settings.agent_fields') },
]);

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
    <SettingsPage
        :title="$t('settings.ticket_settings')"
        :description="pageMeta.description"
    >
        <SettingsSectionNav
            path="/settings/tickets"
            default-section="general"
            :sections="sectionTabs"
            :active-section="activeSection"
        />

        <form @submit.prevent="save">
            <div v-show="activeSection === 'general'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">{{ $t('settings.ticket_numbering') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $t('settings_tickets.new_tickets_use_this_prefix_followed_by_a_sequential_number_e_g_hd-000') }}</p>

                        <div class="mt-4 sm:max-w-xs">
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_tickets.number_prefix') }}</label>
                            <input
                                v-model="form.ticket_number_prefix"
                                type="text"
                                maxlength="20"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase"
                                :placeholder="$t('settings_tickets.hd-')"
                                required
                            />
                            <p v-if="form.errors.ticket_number_prefix" class="mt-1 text-sm text-red-600">{{ form.errors.ticket_number_prefix }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $t('settings_tickets.a_hyphen_is_added_automatically_if_omitted_existing_ticket_numbers_are') }}</p>
                        </div>
                    </div>

            <div v-show="activeSection === 'email'" class="space-y-6">
                        <div class="max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-900">{{ $t('settings_tickets.automatic_first_response') }}</h2>
                                    <p class="mt-1 text-sm text-slate-500">{{ $t('settings_tickets.send_an_acknowledgment_email_when_a_new_email_ticket_is_created') }}</p>
                                </div>
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input v-model="form.auto_first_response_enabled" type="checkbox" class="peer sr-only" />
                                    <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-blue-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500/30 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition after:content-[''] peer-checked:after:translate-x-full" />
                                </label>
                            </div>

                            <div class="mt-4">
                                <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_tickets.message_template') }}</label>
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
                            <h2 class="text-lg font-semibold text-slate-900">{{ $t('settings_tickets.email_blocklist') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('settings_tickets.inbound_email_from_these_addresses_or_domains_will_be_ignored_no_ticke') }}</p>

                            <div class="mt-4">
                                <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_tickets.blocked_senders') }}</label>
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
                        :title="$t('settings.customer_fields')"
                        :description="$t('settings_tickets.optional_fields_shown_when_creating_or_editing_customers')"
                        @add="addField('contact_fields')"
                        @remove="(index) => removeField('contact_fields', index)"
            />

            <CustomFieldEditor
                v-show="activeSection === 'ticket_fields'"
                        :fields="form.ticket_fields"
                        :title="$t('settings.ticket_fields')"
                        :description="$t('settings_tickets.optional_fields_shown_when_creating_or_editing_tickets')"
                        @add="addField('ticket_fields')"
                        @remove="(index) => removeField('ticket_fields', index)"
            />

            <CustomFieldEditor
                v-show="activeSection === 'user_fields'"
                        :fields="form.user_fields"
                        :title="$t('settings_tickets.team_member_fields')"
                        :description="$t('settings_tickets.optional_fields_shown_when_adding_or_editing_team_members')"
                        @add="addField('user_fields')"
                        @remove="(index) => removeField('user_fields', index)"
            />

            <div class="mt-6 flex items-center justify-end border-t border-slate-200 pt-4">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('settings_tickets.save_settings') }}</button>
            </div>
        </form>
    </SettingsPage>
</template>
