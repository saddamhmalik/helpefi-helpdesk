<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppIconAction from '../../Components/AppIconAction.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DataTable from '../../Components/DataTable.vue';
import DataTableMobileCard from '../../Components/ui/DataTableMobileCard.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';
import { useI18n } from 'vue-i18n';

defineProps({
    organizations: Object,
});

const { t } = useI18n();
</script>

<template>
    <Head :title="$t('organizations.organizations')" />
    <AgentLayout>
        <PageHeader :description="$t('organizations.companies_and_account_groupings')">
            <template #actions>
                <a href="/organizations/export" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">{{ $t('organizations.export_csv') }}</a>
                <Link href="/organizations/create" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('organizations.new_organization') }}</Link>
            </template>
        </PageHeader>

        <DataTable>
            <thead class="agent-panel-muted">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('organizations.name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('organizations.domains') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('organizations.contacts') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('organizations.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="org in organizations.data" :key="org.id" class="hover:bg-slate-50 dark:hover:bg-slate-800">
                    <td class="px-4 py-3">
                        <Link :href="`/organizations/${org.id}`" class="font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ org.name }}</Link>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                        {{ org.domains?.map((d) => d.domain).join(', ') || '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ org.contacts_count }}</td>
                    <td class="px-4 py-3">
                        <AppRowActions>
                            <AppIconAction
                                icon="view"
                                variant="primary"
                                :label="$t('organizations.view_organization')"
                                :href="`/organizations/${org.id}`"
                            />
                            <AppEditAction
                                :label="$t('common.edit')"
                                :href="`/organizations/${org.id}`"
                            />
                        </AppRowActions>
                    </td>
                </tr>
                <tr v-if="!organizations.data?.length">
                    <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">{{ $t('organizations.no_organizations_yet') }}</td>
                </tr>
            </tbody>
            <template #mobile>
                <DataTableMobileCard
                    v-for="org in organizations.data"
                    :key="`mobile-${org.id}`"
                    tag="div"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <Link :href="`/organizations/${org.id}`" class="font-medium text-blue-600 dark:text-blue-300">{{ org.name }}</Link>
                            <p class="mt-1 text-xs agent-text-muted">{{ org.domains?.map((d) => d.domain).join(', ') || '—' }}</p>
                            <p class="mt-2 text-xs agent-text-subtle">{{ org.contacts_count }} {{ $t('organizations.contacts').toLowerCase() }}</p>
                        </div>
                        <AppRowActions>
                            <AppIconAction
                                icon="view"
                                variant="primary"
                                :label="$t('organizations.view_organization')"
                                :href="`/organizations/${org.id}`"
                            />
                        </AppRowActions>
                    </div>
                </DataTableMobileCard>
                <div v-if="!organizations.data?.length" class="p-6 text-center text-sm agent-text-muted">
                    {{ $t('organizations.no_organizations_yet') }}
                </div>
            </template>
            <template #footer>
                <PaginationLinks
                    :links="organizations.links"
                    :from="organizations.from"
                    :to="organizations.to"
                    :total="organizations.total"
                />
            </template>
        </DataTable>
    </AgentLayout>
</template>
