<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { readSessionItem, readSessionJson, storageKey, writeSessionItem } from '../support/sessionStorage.js';

const page = usePage();
const { t } = useI18n();

const scope = computed(() => page.props.tenantId ?? '');
const setupDismissedKey = computed(() => storageKey('setup-warnings-modal-dismissed', scope.value));
const noticesSeenKey = computed(() => storageKey('platform-notices-seen-session', scope.value));

const panelOpen = ref(false);
const modalOpen = ref(false);

const warnings = computed(() => page.props.setupWarnings ?? []);

const showWidget = computed(() => {
    if (!warnings.value.length) {
        return false;
    }

    const path = page.url.split('?')[0];

    return path !== '/setup';
});

const countLabel = computed(() => t('components.setup_item_count', { count: warnings.value.length }));

const icons = {
    business_hours: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    email_inbox: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
    email_outbound: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
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

const dismissModal = () => {
    modalOpen.value = false;
    writeSessionItem(setupDismissedKey.value, '1');
};

const trySetupModal = () => {
    if (!showWidget.value) {
        return;
    }

    if (readSessionItem(setupDismissedKey.value) === '1') {
        return;
    }

    if (platformNoticesPending()) {
        return;
    }

    modalOpen.value = true;
};

const onPlatformNoticesComplete = () => {
    trySetupModal();
};

onMounted(() => {
    trySetupModal();
    window.addEventListener('platform-notices-complete', onPlatformNoticesComplete);
});

onUnmounted(() => {
    window.removeEventListener('platform-notices-complete', onPlatformNoticesComplete);
});

watch(showWidget, (visible) => {
    if (visible) {
        trySetupModal();
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="modalOpen && showWidget"
                class="fixed inset-0 z-[190] flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-[2px]"
                role="dialog"
                aria-modal="true"
                :aria-label="t('components.setup_incomplete')"
                @click.self="dismissModal"
            >
                <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-amber-200/80 bg-white shadow-2xl">
                    <div class="border-b border-amber-100 bg-amber-50 px-5 py-4">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">{{ t('components.setup_incomplete') }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ t('components.setup_modal_intro', { count: warnings.length }) }}</p>
                            </div>
                        </div>
                    </div>

                    <ul class="max-h-64 space-y-1 overflow-y-auto px-3 py-3">
                        <li v-for="warning in warnings" :key="warning.key">
                            <Link
                                :href="warning.url"
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition hover:bg-slate-50"
                                @click="dismissModal"
                            >
                                <svg class="h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="iconPath(warning.key)" />
                                </svg>
                                <span class="min-w-0 flex-1 font-medium text-slate-900">{{ warning.title }}</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </li>
                    </ul>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 px-5 py-4 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100"
                            @click="dismissModal"
                        >
                            {{ t('components.remind_me_later') }}
                        </button>
                        <Link
                            href="/setup"
                            class="rounded-lg bg-amber-600 px-4 py-2 text-center text-sm font-semibold text-white transition hover:bg-amber-700"
                            @click="dismissModal"
                        >
                            {{ t('components.setup_guide') }}
                        </Link>
                    </div>
                </div>
            </div>
        </Transition>

        <div
            v-if="showWidget"
            class="pointer-events-none fixed bottom-24 right-4 z-[55]"
        >
            <div class="pointer-events-auto flex flex-col items-end gap-2">
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-2 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="translate-y-2 opacity-0"
                >
                    <div
                        v-if="panelOpen"
                        class="w-72 overflow-hidden rounded-xl border border-amber-200/80 bg-white shadow-xl shadow-slate-900/10"
                    >
                        <div class="flex items-center justify-between border-b border-amber-100 bg-amber-50 px-3 py-2.5">
                            <div>
                                <p class="text-sm font-semibold text-amber-950">{{ t('components.setup_incomplete') }}</p>
                                <p class="text-[11px] text-amber-800">{{ countLabel }}</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-md p-1 text-amber-800 hover:bg-amber-100/80"
                                :aria-label="t('components.close')"
                                @click="panelOpen = false"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <ul class="max-h-56 overflow-y-auto p-2">
                            <li v-for="warning in warnings" :key="warning.key">
                                <Link
                                    :href="warning.url"
                                    class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                    @click="panelOpen = false"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="iconPath(warning.key)" />
                                    </svg>
                                    <span class="truncate">{{ warning.title }}</span>
                                </Link>
                            </li>
                        </ul>
                        <div class="border-t border-slate-100 px-2 py-2">
                            <Link
                                href="/setup"
                                class="block rounded-lg bg-amber-600 px-3 py-2 text-center text-xs font-semibold text-white hover:bg-amber-700"
                                @click="panelOpen = false"
                            >
                                {{ t('components.setup_guide') }}
                            </Link>
                        </div>
                    </div>
                </Transition>

                <button
                    type="button"
                    class="flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900 shadow-md transition hover:bg-amber-100"
                    :aria-expanded="panelOpen"
                    @click="panelOpen = !panelOpen"
                >
                    <svg class="h-4 w-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ t('components.setup') }}</span>
                    <span class="flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-amber-600 px-1.5 text-[10px] font-bold text-white">
                        {{ warnings.length }}
                    </span>
                </button>
            </div>
        </div>
    </Teleport>
</template>
