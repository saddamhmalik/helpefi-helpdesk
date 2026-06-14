<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { readSessionItem, readSessionJson, storageKey, writeSessionItem } from '../support/sessionStorage.js';

const page = usePage();
const { t } = useI18n();

const open = ref(false);
const root = ref(null);

const scope = computed(() => page.props.tenantId ?? '');
const setupSeenKey = computed(() => storageKey('setup-menu-seen', scope.value));
const noticesSeenKey = computed(() => storageKey('platform-notices-seen-session', scope.value));

const warnings = computed(() => page.props.setupWarnings ?? []);

const show = computed(() => {
    if (!warnings.value.length) {
        return false;
    }

    return page.url.split('?')[0] !== '/setup';
});

const countLabel = computed(() => t('components.setup_item_count', { count: warnings.value.length }));

const icons = {
    business_hours: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    email: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    chat_widget: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    invite_team: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    sla_policies: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
};

const iconPath = (key) => icons[key] ?? icons.sla_policies;

const platformNoticesPending = () => {
    const notices = page.props.platformNotices ?? [];

    if (!notices.length) {
        return false;
    }

    const seen = new Set(readSessionJson(noticesSeenKey.value, []));

    return notices.some((notice) => notice.dismissible || !seen.has(notice.id));
};

const toggle = () => {
    open.value = !open.value;

    if (!open.value) {
        markSeen();
    }
};

const close = () => {
    open.value = false;
    markSeen();
};

const markSeen = () => {
    writeSessionItem(setupSeenKey.value, '1');
};

const tryAutoOpen = () => {
    if (!show.value || readSessionItem(setupSeenKey.value) === '1') {
        return;
    }

    if (platformNoticesPending()) {
        return;
    }

    open.value = true;
};

const onPlatformNoticesComplete = () => {
    tryAutoOpen();
};

const onDocumentClick = (event) => {
    if (open.value && root.value && !root.value.contains(event.target)) {
        close();
    }
};

const onDocumentKeydown = (event) => {
    if (open.value && event.key === 'Escape') {
        close();
    }
};

onMounted(() => {
    tryAutoOpen();
    window.addEventListener('platform-notices-complete', onPlatformNoticesComplete);
    document.addEventListener('mousedown', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
});

onUnmounted(() => {
    window.removeEventListener('platform-notices-complete', onPlatformNoticesComplete);
    document.removeEventListener('mousedown', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
});

watch(show, (visible) => {
    if (visible) {
        tryAutoOpen();
    }
});
</script>

<template>
    <div v-if="show" ref="root" class="relative">
        <button
            type="button"
            class="relative rounded-lg p-2 agent-text-subtle transition agent-hover-surface hover:text-amber-700 dark:hover:text-amber-300"
            :aria-label="t('components.setup_incomplete')"
            :aria-expanded="open"
            @click="toggle"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span class="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-semibold text-white">
                {{ warnings.length }}
            </span>
        </button>

        <Transition name="dropdown">
            <div
                v-if="open"
                class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-amber-200/80 bg-white shadow-xl dark:border-amber-900/50 dark:bg-slate-900"
            >
                <div class="flex items-center justify-between border-b border-amber-100 px-3 py-2.5 dark:border-amber-900/40 dark:bg-amber-950/30">
                    <div>
                        <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">{{ t('components.setup_incomplete') }}</p>
                        <p class="text-xs text-amber-800 dark:text-amber-200">{{ countLabel }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-amber-800 transition hover:bg-amber-100 dark:text-amber-200 dark:hover:bg-amber-900/40"
                        :aria-label="t('components.close')"
                        @click="close"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <ul class="max-h-64 overflow-y-auto p-1.5">
                    <li v-for="warning in warnings" :key="warning.key">
                        <Link
                            :href="warning.url"
                            class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition hover:bg-amber-50 dark:hover:bg-amber-950/20"
                            @click="close"
                        >
                            <svg class="h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="iconPath(warning.key)" />
                            </svg>
                            <span class="min-w-0 flex-1 font-medium text-slate-800 dark:text-slate-200">{{ warning.title }}</span>
                            <svg class="h-3.5 w-3.5 shrink-0 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </li>
                </ul>

                <div class="border-t border-slate-100 px-2 py-2 dark:border-slate-800">
                    <Link
                        href="/setup"
                        class="block rounded-lg bg-amber-600 px-3 py-2 text-center text-xs font-semibold text-white transition hover:bg-amber-700"
                        @click="close"
                    >
                        {{ t('components.setup_guide') }}
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>
