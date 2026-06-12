<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';
import { useSettingsNavFilter } from '../composables/useSettingsNavFilter.js';
import { isSettingsNavActive } from '../composables/useSettingsSection.js';

const { t } = useI18n();
const page = usePage();
const { settingsNavGroups } = useAgentNavigation();
const navRef = ref(null);
const query = ref('');

const { filteredGroups } = useSettingsNavFilter(settingsNavGroups, query);

const currentUrl = computed(() => page.url.split('#')[0]);

const navLinkClass = (href) => {
    const active = isSettingsNavActive(href, currentUrl.value);

    if (active) {
        return 'text-slate-900 font-medium';
    }

    return 'text-slate-600 hover:text-slate-900';
};

const iconClass = (href) => isSettingsNavActive(href, currentUrl.value)
    ? 'text-slate-700'
    : 'text-slate-400 group-hover:text-slate-600';
</script>

<template>
    <aside class="settings-sidebar hidden w-full min-w-0 lg:flex lg:min-h-0 lg:max-h-full lg:flex-col lg:overflow-hidden lg:border-r lg:border-slate-200 lg:pr-6">
        <Link
            href="/dashboard"
            class="mb-4 inline-flex shrink-0 items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-slate-800"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            {{ t('components.back_to_app') }}
        </Link>

        <div class="mb-4 shrink-0">
            <h2 class="text-base font-semibold text-slate-900">{{ t('common.settings') }}</h2>
            <div class="relative mt-3">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="query"
                    type="search"
                    :placeholder="t('settings_overview.search_by_name_e_g_domain_billing_sla')"
                    class="w-full rounded-md border-0 bg-slate-100 py-2 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:bg-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-300"
                />
            </div>
        </div>

        <nav ref="navRef" class="settings-sidebar-scroll min-h-0 w-full flex-1 space-y-5 overflow-y-auto pr-1" :aria-label="t('components.settings')">
            <p v-if="query && filteredGroups.length === 0" class="px-2 text-sm text-slate-500">
                {{ t('settings_overview.no_settings_matched') }}
            </p>

            <div v-for="group in filteredGroups" :key="group.id">
                <p class="mb-1 px-2 text-[11px] font-medium text-slate-400">
                    {{ group.label }}
                </p>
                <ul class="w-full space-y-0">
                    <li v-for="item in group.items" :key="item.href" class="w-full">
                        <Link
                            :href="item.href"
                            class="group flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-sm transition-ui"
                            :class="navLinkClass(item.href)"
                            :title="item.description"
                            :aria-label="item.locked ? `${item.label} (${item.lockedLabel})` : item.label"
                            :data-settings-nav-active="isSettingsNavActive(item.href, currentUrl) ? 'true' : undefined"
                        >
                            <svg class="h-4 w-4 shrink-0 transition" :class="iconClass(item.href)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
                            </svg>
                            <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                            <span
                                v-if="item.locked"
                                class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-amber-50 text-amber-700"
                            >
                                {{ item.lockedLabel }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
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
