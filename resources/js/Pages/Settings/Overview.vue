<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsPage from '../../Components/SettingsPage.vue';
import { useAgentNavigation } from '../../composables/useAgentNavigation.js';
import { useSettingsNavFilter } from '../../composables/useSettingsNavFilter.js';

const { t } = useI18n();
const { settingsNavGroups, flatSettingsNavItems } = useAgentNavigation();
const query = ref('');

const { filteredGroups, resultCount } = useSettingsNavFilter(settingsNavGroups, query);
</script>

<template>
    <SettingsPage
        :title="t('common.settings')"
        :description="t('settings_overview.find_workspace_team_channel_and_security_settings_in_one_place')"
    >
        <div class="mb-6 rounded-xl border agent-border agent-panel p-4 shadow-sm">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="settings-search">{{ t('settings_overview.search_settings') }}</label>
            <input
                id="settings-search"
                v-model="query"
                type="search"
                :placeholder="t('settings_overview.search_by_name_e_g_domain_billing_sla')"
                class="mt-2 w-full rounded-lg border agent-border px-3 py-2.5 text-sm agent-text shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
            />
            <p v-if="query" class="mt-2 text-xs agent-text-subtle">
                {{ resultCount }} result{{ resultCount === 1 ? '' : 's' }} for “{{ query }}”
            </p>
        </div>

        <div v-if="filteredGroups.length === 0" class="rounded-xl border border-dashed agent-border bg-white dark:bg-slate-900 px-6 py-12 text-center text-sm agent-text-subtle">
            {{ t('settings_overview.no_settings_matched') }}
        </div>

        <div v-else class="space-y-8">
            <section v-for="group in filteredGroups" :key="group.id">
                <h2 class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">{{ group.label }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="group rounded-xl border agent-border agent-panel p-4 transition hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600 hover:shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-900 agent-text-muted transition group-hover:bg-blue-50 dark:bg-blue-950/40 group-hover:text-blue-600">
                                <svg class="h-[1.125rem] w-[1.125rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-medium agent-text group-hover:text-blue-700 dark:text-blue-300">{{ item.label }}</span>
                                    <span
                                        v-if="item.locked"
                                        class="shrink-0 rounded-full bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300"
                                    >
                                        {{ item.lockedLabel }}
                                    </span>
                                </div>
                                <span v-if="item.description" class="mt-1 block text-xs leading-snug agent-text-subtle">{{ item.description }}</span>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>
        </div>

        <p class="mt-8 text-xs text-slate-400 dark:text-slate-500">
            {{ flatSettingsNavItems.length }} settings available in your workspace.
        </p>
    </SettingsPage>
</template>
