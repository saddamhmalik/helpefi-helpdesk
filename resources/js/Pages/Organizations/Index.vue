<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DataTable from '../../Components/DataTable.vue';
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
                <a href="/organizations/export" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ $t('organizations.export_csv') }}</a>
                <Link href="/organizations/create" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('organizations.new_organization') }}</Link>
            </template>
        </PageHeader>

        <DataTable>
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('organizations.name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('organizations.domains') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('organizations.contacts') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="org in organizations.data" :key="org.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <Link :href="`/organizations/${org.id}`" class="font-medium text-blue-600 hover:text-blue-700">{{ org.name }}</Link>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ org.domains?.map((d) => d.domain).join(', ') || '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ org.contacts_count }}</td>
                </tr>
                <tr v-if="!organizations.data?.length">
                    <td colspan="3" class="px-4 py-12 text-center text-sm text-slate-500">No organizations yet.</td>
                </tr>
            </tbody>
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
