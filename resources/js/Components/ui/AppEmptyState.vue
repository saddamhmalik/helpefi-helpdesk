<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    icon: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'search', 'inbox', 'folder', 'users', 'none'].includes(value),
    },
    size: {
        type: String,
        default: 'default',
        validator: (value) => ['compact', 'default', 'large'].includes(value),
    },
    bordered: { type: Boolean, default: false },
});

const { t } = useI18n();

const resolvedTitle = computed(() => props.title || t('components.no_results_found'));

const containerClass = computed(() => {
    const padding = {
        compact: 'px-4 py-8',
        default: 'px-4 py-12',
        large: 'px-6 py-16',
    }[props.size] ?? 'px-4 py-12';

    const border = props.bordered
        ? 'rounded-xl border border-dashed agent-border bg-slate-50/50 dark:bg-slate-900/30'
        : '';

    return `${padding} ${border}`.trim();
});

const iconWrapClass = computed(() => ({
    compact: 'h-10 w-10',
    default: 'h-12 w-12',
    large: 'h-16 w-16',
}[props.size] ?? 'h-12 w-12'));

const titleClass = computed(() => ({
    compact: 'text-sm',
    default: 'text-sm',
    large: 'text-base',
}[props.size] ?? 'text-sm'));
</script>

<template>
    <div
        class="text-center"
        :class="containerClass"
        role="status"
    >
        <div v-if="$slots.icon || icon !== 'none'" class="mx-auto flex items-center justify-center">
            <slot name="icon">
                <div
                    class="flex items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                    :class="iconWrapClass"
                >
                    <svg
                        v-if="icon === 'search'"
                        class="h-1/2 w-1/2"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.5"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                    </svg>
                    <svg
                        v-else-if="icon === 'inbox'"
                        class="h-1/2 w-1/2"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.5"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <svg
                        v-else-if="icon === 'folder'"
                        class="h-1/2 w-1/2"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.5"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                    </svg>
                    <svg
                        v-else-if="icon === 'users'"
                        class="h-1/2 w-1/2"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.5"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 11-8 0 4 4 0 018 0zm6 4a4 4 0 00-4-4H9a4 4 0 00-4 4" />
                    </svg>
                    <svg
                        v-else
                        class="h-1/2 w-1/2"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.5"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4" />
                    </svg>
                </div>
            </slot>
        </div>

        <slot name="title">
            <p
                class="font-medium text-slate-700 dark:text-slate-300"
                :class="[
                    titleClass,
                    ($slots.icon || icon !== 'none') ? 'mt-4' : '',
                ]"
            >
                {{ resolvedTitle }}
            </p>
        </slot>

        <slot name="description">
            <p
                v-if="description"
                class="mt-1 text-sm agent-text-subtle"
            >
                {{ description }}
            </p>
        </slot>

        <div v-if="$slots.action || $slots.default" class="mt-4 flex flex-col items-center gap-2 sm:flex-row sm:justify-center">
            <slot name="action" />
            <slot />
        </div>
    </div>
</template>
