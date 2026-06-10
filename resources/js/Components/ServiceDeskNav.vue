<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    activeType: {
        type: String,
        default: null,
    },
});

const page = usePage();
const { t } = useI18n();
const currentPath = computed(() => page.url.split('?')[0]);

const tabs = computed(() => [
    { label: t('components.service_desk_overview'), href: '/service-desk', match: (path) => path === '/service-desk' },
    { label: t('components.service_desk_approvals'), href: '/service-desk/approvals', match: (path) => path.startsWith('/service-desk/approvals') },
    { label: t('components.service_desk_calendar'), href: '/service-desk/changes/calendar', match: (path) => path.startsWith('/service-desk/changes/calendar') },
    { label: t('components.service_desk_major_incidents'), href: '/service-desk/major-incidents', match: (path) => path.startsWith('/service-desk/major-incidents') },
    { label: t('components.service_desk_incidents'), href: '/service-desk/queues/incident', type: 'incident', match: (path) => path === '/service-desk/queues/incident' },
    { label: t('components.service_desk_requests'), href: '/service-desk/queues/service_request', type: 'service_request', match: (path) => path === '/service-desk/queues/service_request' },
    { label: t('components.service_desk_changes'), href: '/service-desk/queues/change', type: 'change', match: (path) => path === '/service-desk/queues/change' },
    { label: t('components.service_desk_problems'), href: '/service-desk/queues/problem', type: 'problem', match: (path) => path === '/service-desk/queues/problem' },
]);

const isActive = (tab) => {
    if (props.activeType && tab.type) {
        return tab.type === props.activeType;
    }

    return tab.match(currentPath.value);
};
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-3" :aria-label="$t('components.service_desk_sections')">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="isActive(tab)
                ? 'bg-slate-900 text-white'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
