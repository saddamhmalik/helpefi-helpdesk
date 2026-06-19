<script setup>
import { Link } from '@inertiajs/vue3';
import AppCollapse from './AppCollapse.vue';
import { isSettingsNavActive } from '../composables/useSettingsSection.js';

defineProps({
    groups: { type: Array, required: true },
    currentUrl: { type: String, required: true },
    isExpanded: { type: Function, required: true },
    toggle: { type: Function, required: true },
    navLinkClass: { type: Function, required: true },
    iconClass: { type: Function, default: null },
    showIcons: { type: Boolean, default: false },
});

const emit = defineEmits(['navigate']);
</script>

<template>
    <div v-for="group in groups" :key="group.id" class="w-full">
        <button
            type="button"
            class="flex w-full items-center gap-1 rounded-md px-2 py-1.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-400 transition hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300"
            :aria-expanded="isExpanded(group.id)"
            @click="toggle(group.id)"
        >
            <svg
                class="h-3 w-3 shrink-0 transition-transform duration-200"
                :class="isExpanded(group.id) ? 'rotate-90' : ''"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
            <span class="min-w-0 flex-1 truncate">{{ group.label }}</span>
            <span class="text-[10px] font-normal normal-case tracking-normal text-slate-400 dark:text-slate-500">{{ group.items.length }}</span>
        </button>

        <AppCollapse :open="isExpanded(group.id)">
            <ul class="mb-2 ml-1 space-y-0 border-l border-slate-200 pl-2 dark:border-slate-700">
                <li v-for="item in group.items" :key="item.href" class="w-full">
                    <Link
                        :href="item.href"
                        class="group flex w-full items-center gap-2 rounded-md py-1.5 pl-2 pr-1 text-[13px] transition"
                        :class="navLinkClass(item.href)"
                        :title="item.description"
                        :aria-label="item.locked ? `${item.label} (${item.lockedLabel})` : item.label"
                        :data-settings-nav-active="isSettingsNavActive(item.href, currentUrl) ? 'true' : undefined"
                        @click="emit('navigate')"
                    >
                        <svg
                            v-if="showIcons && iconClass"
                            class="h-3.5 w-3.5 shrink-0 transition"
                            :class="iconClass(item.href)"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
                        </svg>
                        <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                        <span
                            v-if="item.locked"
                            class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
                        >
                            {{ item.lockedLabel }}
                        </span>
                    </Link>
                </li>
            </ul>
        </AppCollapse>
    </div>
</template>
