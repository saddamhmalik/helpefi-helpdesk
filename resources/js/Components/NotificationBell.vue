<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const open = ref(false);

const summary = computed(() => page.props.notifications ?? { unread_count: 0, recent: [] });

const toggle = () => {
    open.value = !open.value;
};

const markRead = (id, url) => {
    router.post(`/notifications/${id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (url) {
                router.visit(url);
            }
        },
    });
    open.value = false;
};

const markAllRead = () => {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
    open.value = false;
};
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Notifications"
            @click="toggle"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span
                v-if="summary.unread_count > 0"
                class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
            >
                {{ summary.unread_count > 9 ? '9+' : summary.unread_count }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
        >
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <p class="text-sm font-semibold text-slate-900">Notifications</p>
                <button
                    v-if="summary.unread_count > 0"
                    type="button"
                    class="text-xs text-blue-600 hover:text-blue-700"
                    @click="markAllRead"
                >
                    Mark all read
                </button>
            </div>

            <div class="max-h-80 overflow-y-auto">
                <button
                    v-for="item in summary.recent"
                    :key="item.id"
                    type="button"
                    class="block w-full border-b border-slate-50 px-4 py-3 text-left hover:bg-slate-50"
                    :class="item.read_at ? 'opacity-70' : 'bg-blue-50/40'"
                    @click="markRead(item.id, item.url)"
                >
                    <p class="text-sm text-slate-800">{{ item.message }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ new Date(item.created_at).toLocaleString() }}</p>
                </button>
                <p v-if="!summary.recent.length" class="px-4 py-6 text-center text-sm text-slate-500">No notifications yet.</p>
            </div>

            <div class="border-t border-slate-100 px-4 py-2">
                <Link href="/notifications" class="text-xs font-medium text-blue-600 hover:text-blue-700" @click="open = false">
                    View all
                </Link>
            </div>
        </div>
    </div>
</template>
