<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ListPanel from '../../Components/ListPanel.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';

defineProps({
    notifications: Object,
});

const markRead = (id) => {
    router.post(`/notifications/${id}/read`, {}, { preserveScroll: true });
};

const markAllRead = () => {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Notifications" />
    <AgentLayout>
        <PageHeader description="Your recent alerts and updates.">
            <template #actions>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="markAllRead">
                    Mark all read
                </button>
            </template>
        </PageHeader>

        <ListPanel>
            <div
                v-for="item in notifications.data"
                :key="item.id"
                class="flex items-start justify-between gap-4 border-b border-slate-100 px-1 py-4 last:border-b-0"
                :class="item.read_at ? '' : 'rounded-lg bg-blue-50/30 px-3'"
            >
                <div>
                    <p class="text-sm text-slate-900">{{ item.message }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ new Date(item.created_at).toLocaleString() }}</p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Link v-if="item.url" :href="item.url" class="text-xs font-medium text-blue-600 hover:text-blue-700">Open</Link>
                    <button v-if="!item.read_at" type="button" class="text-xs text-slate-500 hover:text-slate-700" @click="markRead(item.id)">
                        Mark read
                    </button>
                </div>
            </div>
            <p v-if="!notifications.data?.length" class="py-12 text-center text-sm text-slate-500">No notifications yet.</p>

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
