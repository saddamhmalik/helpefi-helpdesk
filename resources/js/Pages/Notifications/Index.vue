<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ListPanel from '../../Components/ListPanel.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';
import NotificationTypeIcon from '../../Components/NotificationTypeIcon.vue';
import { useI18n } from 'vue-i18n';
import { formatRelativeTime, notificationMeta, notificationFilterTypes } from '../../composables/useNotificationMeta.js';

const props = defineProps({
    notifications: Object,
    filters: {
        type: Object,
        default: () => ({ unread: false, type: null }),
    },
});

const { t, locale } = useI18n();

const filterTabs = computed(() => [
    { key: 'all', label: t('notifications.filter_all'), unread: false, type: null },
    { key: 'unread', label: t('notifications.filter_unread'), unread: true, type: null },
    ...notificationFilterTypes().map((type) => ({
        key: type,
        label: t(notificationMeta(type).labelKey),
        unread: false,
        type,
    })),
]);

const activeTab = computed(() => {
    if (props.filters.unread) {
        return 'unread';
    }

    if (props.filters.type) {
        return props.filters.type;
    }

    return 'all';
});

const applyFilter = (tab) => {
    const query = {};

    if (tab.unread) {
        query.unread = 1;
    }

    if (tab.type) {
        query.type = tab.type;
    }

    router.get('/notifications', query, { preserveState: true, preserveScroll: true });
};

const markRead = (id) => {
    router.post(`/notifications/${id}/read`, {}, { preserveScroll: true });
};

const openNotification = (item) => {
    router.post(`/notifications/${item.id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (item.url) {
                router.visit(item.url);
            }
        },
    });
};

const markAllRead = () => {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
};

const clearRead = () => {
    if (!window.confirm(t('notifications.clear_read_confirm'))) {
        return;
    }

    router.post('/notifications/clear-read', {}, { preserveScroll: true });
};

const relativeTime = (value) => formatRelativeTime(value, locale.value);
const typeLabel = (type) => t(notificationMeta(type).labelKey);

const hasReadItems = computed(() => props.notifications.data?.some((item) => item.read_at));
</script>

<template>
    <Head :title="$t('notifications.notifications')" />
    <AgentLayout>
        <PageHeader
            :title="$t('notifications.notifications')"
            :description="$t('notifications.your_recent_alerts_and_updates')"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="hasReadItems"
                        type="button"
                        class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        @click="clearRead"
                    >
                        {{ $t('notifications.clear_read') }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        @click="markAllRead"
                    >
                        {{ $t('notifications.mark_all_read') }}
                    </button>
                </div>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="tab in filterTabs"
                :key="tab.key"
                type="button"
                class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                :class="activeTab === tab.key
                    ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                    : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'"
                @click="applyFilter(tab)"
            >
                {{ tab.label }}
            </button>
        </div>

        <ListPanel>
            <div
                v-for="item in notifications.data"
                :key="item.id"
                class="flex items-center gap-4 border-b border-slate-100 dark:border-slate-800 px-4 py-4 last:border-b-0"
                :class="item.read_at ? '' : 'bg-blue-50/80 dark:bg-blue-950/30'"
            >
                <NotificationTypeIcon :type="item.type" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                            :class="notificationMeta(item.type).badgeClass"
                        >
                            {{ typeLabel(item.type) }}
                        </span>
                        <span v-if="item.ticket_number" class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ item.ticket_number }}</span>
                        <span v-if="!item.read_at" class="h-2 w-2 rounded-full bg-blue-500" />
                    </div>
                    <p class="mt-1 text-sm font-medium leading-snug text-slate-900 dark:text-slate-100">{{ item.message }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ relativeTime(item.created_at) }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        v-if="!item.read_at"
                        type="button"
                        class="hidden rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300 sm:inline-flex"
                        @click="markRead(item.id)"
                    >
                        {{ $t('notifications.mark_read') }}
                    </button>
                    <button
                        v-if="item.url"
                        type="button"
                        class="inline-flex items-center rounded-lg border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 px-3 py-1.5 text-xs font-semibold text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/60"
                        @click="openNotification(item)"
                    >
                        {{ $t('common.open') }}
                    </button>
                </div>
            </div>
            <p v-if="!notifications.data?.length" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ $t('notifications.no_notifications_yet') }}
            </p>

            <template #footer>
                <PaginationLinks
                    :links="notifications.links"
                    :from="notifications.from"
                    :to="notifications.to"
                    :total="notifications.total"
                />
            </template>
        </ListPanel>
    </AgentLayout>
</template>
