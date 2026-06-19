<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { isServiceDeskNavActive, prepareServiceDeskNavItems } from '../composables/serviceDeskNavigation.js';

const props = defineProps({
    activeType: {
        type: String,
        default: null,
    },
});

const page = usePage();
const { t, te } = useI18n();
const user = computed(() => page.props.auth.user);
const billing = computed(() => page.props.billing);

const hasPermission = (permission) => {
    if (user.value?.is_admin) {
        return true;
    }

    return user.value?.permissions?.includes(permission) ?? false;
};

const hasFeature = (feature) => billing.value?.features?.includes(feature) ?? true;

const tabs = computed(() => prepareServiceDeskNavItems({
    t,
    te,
    hasPermission,
    hasFeature,
}));

const isActive = (tab) => {
    if (props.activeType && tab.queueType) {
        return tab.queueType === props.activeType;
    }

    return isServiceDeskNavActive(tab.href, page.url, tab.exact);
};
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2 border-b agent-border pb-3" :aria-label="$t('components.service_desk_sections')">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="isActive(tab)
                ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-100 dark:text-slate-900'
                : 'agent-text-muted agent-hover-surface hover:text-slate-900 dark:hover:text-slate-100'"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
