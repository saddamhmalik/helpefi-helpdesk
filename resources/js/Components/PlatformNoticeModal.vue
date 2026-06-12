<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { readSessionJson, storageKey, writeSessionItem, writeSessionJson } from '../support/sessionStorage.js';

const page = usePage();
const { t } = useI18n();

const scope = computed(() => page.props.tenantId ?? '');
const seenStorageKey = computed(() => storageKey('platform-notices-seen-session', scope.value));
const completeStorageKey = computed(() => storageKey('platform-notices-modal-complete', scope.value));

const modalOpen = ref(false);
const queue = ref([]);
const dismissing = ref(false);
const totalInQueue = ref(0);

const styles = {
    maintenance: {
        header: 'border-amber-100 bg-amber-50',
        icon: 'bg-amber-100 text-amber-700',
        badge: 'bg-amber-200/60 text-amber-900',
        title: 'text-slate-900',
        body: 'text-amber-900',
        button: 'bg-amber-600 hover:bg-amber-700',
    },
    offer: {
        header: 'border-emerald-100 bg-emerald-50',
        icon: 'bg-emerald-100 text-emerald-700',
        badge: 'bg-emerald-200/60 text-emerald-900',
        title: 'text-slate-900',
        body: 'text-emerald-900',
        button: 'bg-emerald-600 hover:bg-emerald-700',
    },
    announcement: {
        header: 'border-blue-100 bg-blue-50',
        icon: 'bg-blue-100 text-blue-700',
        badge: 'bg-blue-200/60 text-blue-900',
        title: 'text-slate-900',
        body: 'text-blue-900',
        button: 'bg-blue-600 hover:bg-blue-700',
    },
    general: {
        header: 'border-slate-100 bg-slate-50',
        icon: 'bg-slate-100 text-slate-600',
        badge: 'bg-slate-200/60 text-slate-800',
        title: 'text-slate-900',
        body: 'text-slate-800',
        button: 'bg-slate-700 hover:bg-slate-800',
    },
};

const typeLabelKeys = {
    maintenance: 'notice_maintenance',
    offer: 'notice_offer',
    announcement: 'notice_announcement',
    general: 'notice_general',
};

const current = computed(() => queue.value[0] ?? null);

const currentIndex = computed(() => Math.max(1, totalInQueue.value - queue.value.length + 1));

const styleFor = (notice) => styles[notice?.notice_type] ?? styles.general;

const typeLabel = (noticeType) => t(`components.${typeLabelKeys[noticeType] ?? 'notice_general'}`);

const readSeenIds = () => readSessionJson(seenStorageKey.value, []);

const markSeen = (id) => {
    const seen = new Set(readSeenIds());
    seen.add(id);
    writeSessionJson(seenStorageKey.value, [...seen]);
};

const markQueueComplete = () => {
    writeSessionItem(completeStorageKey.value, '1');
    window.dispatchEvent(new CustomEvent('platform-notices-complete'));
};

const buildQueue = () => {
    const notices = page.props.platformNotices ?? [];
    const seen = new Set(readSeenIds());

    queue.value = notices.filter((notice) => {
        if (notice.dismissible) {
            return true;
        }

        return !seen.has(notice.id);
    });

    totalInQueue.value = queue.value.length;
    modalOpen.value = queue.value.length > 0;

    if (!queue.value.length) {
        markQueueComplete();
    }
};

const closeModal = () => {
    modalOpen.value = false;

    if (!queue.value.length) {
        markQueueComplete();
    }
};

const advanceQueue = () => {
    queue.value.shift();

    if (queue.value.length) {
        modalOpen.value = true;
        return;
    }

    closeModal();
};

const dismiss = async (notice) => {
    if (dismissing.value) {
        return;
    }

    if (notice.dismissible) {
        dismissing.value = true;

        router.post(`/platform-notices/${notice.id}/dismiss`, {}, {
            preserveScroll: true,
            onFinish: () => {
                dismissing.value = false;
                advanceQueue();
            },
        });

        return;
    }

    markSeen(notice.id);
    advanceQueue();
};

onMounted(buildQueue);

watch(() => page.props.platformNotices, buildQueue, { deep: true });
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
                v-if="modalOpen && current"
                class="fixed inset-0 z-[195] flex items-center justify-center bg-slate-900/45 p-4 backdrop-blur-[2px]"
                role="dialog"
                aria-modal="true"
                :aria-label="current.title"
            >
                <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="border-b px-5 py-4" :class="styleFor(current).header">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="styleFor(current).icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-semibold" :class="styleFor(current).title">{{ current.title }}</h2>
                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="styleFor(current).badge">
                                        {{ typeLabel(current.notice_type) }}
                                    </span>
                                    <span
                                        v-if="current.priority === 'high'"
                                        class="rounded bg-red-200/70 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-900"
                                    >
                                        {{ t('components.important') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="max-h-[min(60vh,24rem)] overflow-y-auto px-5 py-4">
                        <img
                            v-if="current.image_url"
                            :src="current.image_url"
                            :alt="current.title"
                            class="mb-4 max-h-48 w-full rounded-xl border border-slate-200 object-contain"
                        />
                        <div
                            v-if="current.body_html"
                            class="prose prose-sm max-w-none"
                            :class="styleFor(current).body"
                            v-html="current.body_html"
                        />
                        <p v-else class="text-sm text-slate-600">{{ t('components.platform_notice_fallback') }}</p>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                        <p v-if="totalInQueue > 1" class="text-xs text-slate-400">
                            {{ t('components.notice_queue_progress', { current: currentIndex, total: totalInQueue }) }}
                        </p>
                        <span v-else />

                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-60"
                            :class="styleFor(current).button"
                            :disabled="dismissing"
                            @click="dismiss(current)"
                        >
                            {{ current.dismissible ? t('components.dismiss') : t('components.got_it') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
