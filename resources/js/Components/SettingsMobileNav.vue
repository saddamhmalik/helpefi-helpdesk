<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';
import { useSettingsNavFilter } from '../composables/useSettingsNavFilter.js';
import { isSettingsNavActive } from '../composables/useSettingsSection.js';
import { useBodyScrollLock, useEscapeKey } from '../composables/useModal.js';

const props = defineProps({
    breadcrumb: { type: Object, default: null },
});

const { t } = useI18n();
const page = usePage();
const { settingsNavGroups } = useAgentNavigation();
const open = ref(false);
const query = ref('');

const { filteredGroups } = useSettingsNavFilter(settingsNavGroups, query);
const currentUrl = computed(() => page.url.split('#')[0]);

const currentLabel = computed(() => props.breadcrumb?.page ?? t('common.settings'));
const currentGroup = computed(() => props.breadcrumb?.group ?? null);

watch(currentUrl, () => {
    open.value = false;
    query.value = '';
});

const navLinkClass = (href) => {
    const active = isSettingsNavActive(href, currentUrl.value);

    if (active) {
        return 'border-blue-600 bg-blue-50 text-blue-900';
    }

    return 'border-transparent text-slate-700 hover:bg-slate-50';
};

const iconClass = (href) => (isSettingsNavActive(href, currentUrl.value) ? 'text-blue-600' : 'text-slate-400');

const close = () => {
    open.value = false;
    query.value = '';
};

useBodyScrollLock(open);
useEscapeKey(open, close);
</script>

<template>
    <div class="lg:hidden">
        <div class="sticky top-0 z-20 -mx-4 mb-4 border-b border-slate-200/80 bg-white/95 px-4 py-2 backdrop-blur supports-[backdrop-filter]:bg-white/80 sm:-mx-6 sm:px-6">
            <div class="flex items-stretch gap-2">
                <button
                    type="button"
                    class="flex min-w-0 flex-1 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-left shadow-sm transition active:bg-slate-100"
                    :aria-expanded="open"
                    @click="open = true"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-slate-500 ring-1 ring-slate-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span v-if="currentGroup" class="block truncate text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                            {{ currentGroup }}
                        </span>
                        <span class="block truncate text-sm font-semibold text-slate-900">{{ currentLabel }}</span>
                    </span>
                    <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <Link
                    href="/settings"
                    class="flex shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-slate-600 shadow-sm transition hover:bg-slate-50"
                    :title="t('common.all_settings')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                </Link>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="slide-over">
                <div v-if="open" class="fixed inset-0 z-50 lg:hidden">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[1px]" @click="close" />

                    <aside class="slide-over-panel absolute inset-y-0 left-0 flex w-[min(100vw-2.5rem,20rem)] flex-col bg-white shadow-2xl">
                    <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">{{ t('common.settings') }}</p>
                            <p class="truncate text-sm font-semibold text-slate-900">{{ t('common.browse_settings') }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100"
                            @click="close"
                        >
                            <span class="sr-only">{{ t('common.close') }}</span>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="shrink-0 border-b border-slate-100 px-4 py-3">
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                v-model="query"
                                type="search"
                                :placeholder="t('settings_overview.search_settings')"
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100"
                            />
                        </div>
                    </div>

                    <nav class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-3 py-3">
                        <p v-if="query && filteredGroups.length === 0" class="px-2 py-6 text-center text-sm text-slate-500">
                            {{ t('settings_overview.no_settings_matched') }}
                        </p>

                        <div v-for="group in filteredGroups" :key="group.id">
                            <p class="mb-1 px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                                {{ group.label }}
                            </p>
                            <ul class="space-y-0.5">
                                <li v-for="item in group.items" :key="item.href">
                                    <Link
                                        :href="item.href"
                                        class="flex items-center gap-2.5 rounded-lg border-l-2 px-2.5 py-2.5 text-sm font-medium transition"
                                        :class="navLinkClass(item.href)"
                                        :aria-label="item.locked ? `${item.label} (${item.lockedLabel})` : item.label"
                                        @click="close"
                                    >
                                        <svg class="h-4 w-4 shrink-0" :class="iconClass(item.href)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
                                        </svg>
                                        <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                                        <span
                                            v-if="item.locked"
                                            class="shrink-0 rounded-full bg-amber-50 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-amber-700"
                                        >
                                            {{ item.lockedLabel }}
                                        </span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </nav>

                    <div class="shrink-0 border-t border-slate-200 p-3">
                        <Link
                            href="/dashboard"
                            class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                            @click="close"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            {{ t('components.back_to_app') }}
                        </Link>
                    </div>
                </aside>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
