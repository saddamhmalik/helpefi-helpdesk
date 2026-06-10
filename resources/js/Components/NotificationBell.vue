<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import NotificationTypeIcon from './NotificationTypeIcon.vue';
import { useNotificationInbox } from '../composables/useNotificationInbox.js';
import { formatRelativeTime, formatUnreadCount, notificationMeta } from '../composables/useNotificationMeta.js';

const { t, locale } = useI18n();
const open = ref(false);
const root = ref(null);

const { summary, init, markItemReadLocally, markAllReadLocally } = useNotificationInbox();

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
    init();
    document.addEventListener('mousedown', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
});

const toggle = () => {
    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

const unreadLabel = computed(() => formatUnreadCount(summary.unread_count));

const markRead = (item) => {
    markItemReadLocally(item.id);

    router.post(`/notifications/${item.id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (item.url) {
                router.visit(item.url);
            }
        },
    });

    close();
};

const markAllRead = () => {
    markAllReadLocally();
    router.post('/notifications/read-all', {}, { preserveScroll: true });
    close();
};

const typeLabel = (type) => t(notificationMeta(type).labelKey);
const relativeTime = (value) => formatRelativeTime(value, locale.value);
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
            :aria-label="$t('components.notifications')"
            :aria-expanded="open"
            @click="toggle"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span
                v-if="summary.unread_count > 0"
                class="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
            >
                {{ unreadLabel }}
            </span>
        </button>

        <Transition name="dropdown">
            <div
                v-if="open"
                class="absolute right-0 z-50 mt-2 w-96 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
            >
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ $t('components.notifications') }}</p>
                    <p v-if="summary.unread_count > 0" class="text-xs text-slate-500">
                        {{ $t('components.unread_count', { count: summary.unread_count }) }}
                    </p>
                </div>
                <button
                    v-if="summary.unread_count > 0"
                    type="button"
                    class="text-xs font-medium text-blue-600 hover:text-blue-700"
                    @click="markAllRead"
                >
                    {{ $t('components.mark_all_read') }}
                </button>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <button
                    v-for="item in summary.recent"
                    :key="item.id"
                    type="button"
                    class="flex w-full items-center gap-3 border-b border-slate-50 px-4 py-3 text-left transition hover:bg-slate-50"
                    :class="item.read_at ? 'opacity-75' : 'bg-blue-50/30'"
                    @click="markRead(item)"
                >
                    <NotificationTypeIcon :type="item.type" size-class="h-4 w-4" box-class="h-9 w-9" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                :class="notificationMeta(item.type).badgeClass"
                            >
                                {{ typeLabel(item.type) }}
                            </span>
                            <span v-if="item.ticket_number" class="font-mono text-[10px] text-slate-500">
                                {{ item.ticket_number }}
                            </span>
                            <span v-if="!item.read_at" class="h-2 w-2 rounded-full bg-blue-500" />
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm font-medium leading-snug text-slate-800">{{ item.message }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ relativeTime(item.created_at) }}</p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <p v-if="!summary.recent.length" class="px-4 py-8 text-center text-sm text-slate-500">
                    {{ $t('components.no_notifications_yet') }}
                </p>
            </div>

            <div class="border-t border-slate-100 px-4 py-2.5">
                <Link href="/notifications" class="text-xs font-medium text-blue-600 hover:text-blue-700" @click="close">
                    {{ $t('components.view_all') }}
                </Link>
            </div>
            </div>
        </Transition>
    </div>
</template>
