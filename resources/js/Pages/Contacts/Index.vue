<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppIconAction from '../../Components/AppIconAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppTabs from '../../Components/AppTabs.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ListPanel from '../../Components/ListPanel.vue';
import FilterField from '../../Components/FilterField.vue';
import DataTable from '../../Components/DataTable.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    contacts: Object,
    filters: Object,
    stats: Object,
});

const { t } = useI18n();

const page = usePage();
const isAdmin = () => page.props.auth.user?.is_admin;

const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const accessTabs = [
    { id: 'all', label: t('contacts.all'), count: props.stats?.total },
    { id: 'portal', label: t('contacts.portal_access'), count: props.stats?.portal },
    { id: 'guest', label: t('contacts.guest_only'), count: props.stats?.guest },
];

const inputClass = 'w-full max-w-md rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';

const applyFilters = (overrides = {}) => {
    router.get('/contacts', {
        search: props.filters.search || undefined,
        access: props.filters.access || 'all',
        ...overrides,
    }, { preserveState: true, replace: true });
};

const search = (event) => {
    applyFilters({ search: event.target.value || undefined });
};

const setAccess = (access) => {
    applyFilters({ access });
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (props.filters.search) {
        params.set('search', props.filters.search);
    }

    if (props.filters.access && props.filters.access !== 'all') {
        params.set('access', props.filters.access);
    }

    const query = params.toString();

    return query ? `/contacts/export?${query}` : '/contacts/export';
});

const removePortalAccess = (contact) => {
    askConfirm({
        title: t('contacts.revoke_portal_access'),
        message: `Remove portal login for ${contact.name}? They can still email support, but cannot sign in.`,
        confirmLabel: 'Revoke access',
        variant: 'danger',
        action: () => router.delete(`/customers/accounts/${contact.portal_user.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <Head :title="$t('contacts.customers')" />
    <AgentLayout>
        <PageHeader :description="$t('contacts.everyone_who_interacts_with_support_portal_access_means_they_can_sign_')">
            <template #actions>
                <a
                    :href="exportUrl"
                    class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                >
                    {{ $t('contacts.export_csv') }}
                </a>
                <Link href="/contacts/create" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('contacts.new_customer') }}</Link>
            </template>
        </PageHeader>

        <AppTabs
            class="mb-4"
            :model-value="filters.access || 'all'"
            variant="pills"
            :items="accessTabs.map((tab) => ({ ...tab, badge: tab.count ?? 0 }))"
            @update:model-value="setAccess"
        />

        <ListPanel class="mb-4" :title="$t('contacts.find_customers')">
            <FilterField :label="$t('contacts.search')">
                <input
                    type="search"
                    :value="filters.search || ''"
                    :placeholder="$t('contacts.search_by_name_or_email')"
                    :class="inputClass"
                    @input="search"
                />
            </FilterField>
        </ListPanel>

        <DataTable>
            <thead class="agent-panel-muted">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('contacts.customer') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('contacts.access') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('contacts.organization') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('contacts.tags') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('contacts.tickets') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('contacts.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-slate-50 dark:hover:bg-slate-800">
                    <td class="px-4 py-3">
                        <Link :href="`/contacts/${contact.id}`" class="font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ contact.name }}</Link>
                        <p v-if="contact.email" class="text-xs text-slate-500 dark:text-slate-400">{{ contact.email }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span
                            v-if="contact.portal_user"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-950/40 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 ring-1 ring-inset ring-indigo-600/15"
                        >
                            {{ $t('contacts.portal') }}
                        </span>
                        <span v-else class="inline-flex rounded-full bg-slate-100 dark:bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ $t('contacts.guest') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ contact.organization?.name || '—' }}</td>
                    <td class="px-4 py-3">
                        <span v-for="tag in contact.tags" :key="tag.id" class="mr-1 inline-block rounded-full bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-xs text-slate-700 dark:text-slate-300">{{ tag.name }}</span>
                        <span v-if="!contact.tags?.length" class="text-sm text-slate-400 dark:text-slate-500">—</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ contact.tickets_count }}</td>
                    <td class="px-4 py-3">
                        <AppRowActions>
                            <AppIconAction
                                icon="view"
                                variant="primary"
                                :label="$t('contacts.view_customer')"
                                :href="`/contacts/${contact.id}`"
                            />
                            <AppDeleteAction
                                v-if="isAdmin() && contact.portal_user"
                                :label="$t('contacts.revoke_portal')"
                                @click="removePortalAccess(contact)"
                            />
                        </AppRowActions>
                    </td>
                </tr>
                <tr v-if="!contacts.data.length">
                    <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                        No customers match your search.
                    </td>
                </tr>
            </tbody>
            <template #footer>
                <PaginationLinks
                    :links="contacts.links"
                    :from="contacts.from"
                    :to="contacts.to"
                    :total="contacts.total"
                />
            </template>
        </DataTable>

        <AppConfirmDialog
            :open="confirm.open"
            :title="confirm.title"
            :message="confirm.message"
            :confirm-label="confirm.confirmLabel"
            :variant="confirm.variant"
            @close="closeConfirm"
            @confirm="onConfirm"
        />
    </AgentLayout>
</template>
