<script setup>
import { notificationMeta } from '../composables/useNotificationMeta.js';

defineProps({
    type: { type: String, default: 'unknown' },
    sizeClass: { type: String, default: 'h-5 w-5' },
    boxClass: { type: String, default: 'h-10 w-10' },
});

const icons = {
    assignment: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    reply: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6',
    alert: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    approval: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    notice: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    bell: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
};
</script>

<template>
    <span
        class="flex shrink-0 items-center justify-center rounded-xl"
        :class="[boxClass, notificationMeta(type).badgeClass]"
    >
        <svg
            :class="[sizeClass, notificationMeta(type).iconClass]"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="icons[notificationMeta(type).icon]" />
        </svg>
    </span>
</template>
