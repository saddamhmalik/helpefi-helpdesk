<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsNavTree from './SettingsNavTree.vue';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';
import { useSettingsNavFilter } from '../composables/useSettingsNavFilter.js';
import { useSettingsNavExpand } from '../composables/useSettingsNavExpand.js';
import { useSettingsNavScroll } from '../composables/useSettingsNavScroll.js';
import { isSettingsNavActive } from '../composables/useSettingsSection.js';

const { t } = useI18n();
const page = usePage();
const { settingsNavGroups } = useAgentNavigation();
const navRef = ref(null);
const query = ref('');

const { filteredGroups } = useSettingsNavFilter(settingsNavGroups, query);
const currentUrl = computed(() => page.url.split('#')[0]);
const { isExpanded, toggle } = useSettingsNavExpand(filteredGroups, currentUrl, query);

useSettingsNavScroll(navRef, currentUrl);

const navLinkClass = (href) => {
    const active = isSettingsNavActive(href, currentUrl.value);

    if (active) {
        return 'agent-text font-medium';
    }

    return 'agent-text-muted hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-200';
};
</script>

<template>
    <aside class="settings-sidebar hidden w-full min-w-0 lg:flex lg:min-h-0 lg:max-h-full lg:flex-col lg:overflow-hidden lg:border-r lg:border-slate-200 lg:pl-4 dark:border-slate-800 lg:dark:border-slate-700 lg:pr-4">
        <Link
            href="/dashboard"
            class="mb-4 inline-flex shrink-0 items-center gap-2 text-sm font-medium agent-text-subtle transition hover:text-slate-800 dark:text-slate-200 dark:hover:text-slate-200"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            {{ t('components.back_to_app') }}
        </Link>

        <div class="mb-4 shrink-0">
            <h2 class="text-base font-semibold agent-text">{{ t('common.settings') }}</h2>
            <div class="relative mt-3">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="query"
                    type="search"
                    :placeholder="t('settings_overview.search_by_name_e_g_domain_billing_sla')"
                    class="w-full rounded-md border-0 agent-panel-muted py-2 pl-9 pr-3 text-sm agent-text placeholder:text-slate-400 dark:text-slate-500 focus:bg-slate-100 dark:bg-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-300 dark:focus:bg-slate-800 dark:focus:ring-slate-600"
                />
            </div>
        </div>

        <nav ref="navRef" class="settings-sidebar-scroll min-h-0 w-full flex-1 space-y-1 overflow-y-auto pr-1" :aria-label="t('components.settings')">
            <p v-if="query && filteredGroups.length === 0" class="px-2 text-sm agent-text-subtle">
                {{ t('settings_overview.no_settings_matched') }}
            </p>

            <SettingsNavTree
                :groups="filteredGroups"
                :current-url="currentUrl"
                :is-expanded="isExpanded"
                :toggle="toggle"
                :nav-link-class="navLinkClass"
            />
        </nav>
    </aside>
</template>

<style scoped>
.settings-sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
}

.settings-sidebar-scroll::-webkit-scrollbar {
    width: 6px;
}

.settings-sidebar-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background-color: rgb(203 213 225);
}
</style>
