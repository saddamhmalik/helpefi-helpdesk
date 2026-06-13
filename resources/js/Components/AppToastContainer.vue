<script setup>
import { useToast } from '../composables/useToast.js';

const toast = useToast();

const styles = {
    success: {
        wrap: 'border-emerald-200 bg-emerald-50 text-emerald-950 shadow-emerald-900/10 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-50 dark:shadow-black/40',
        icon: 'text-emerald-600 dark:text-emerald-400',
        path: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    error: {
        wrap: 'border-red-200 bg-red-50 text-red-950 shadow-red-900/10 dark:border-red-700 dark:bg-red-950 dark:text-red-50 dark:shadow-black/40',
        icon: 'text-red-600 dark:text-red-400',
        path: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    warning: {
        wrap: 'border-amber-200 bg-amber-50 text-amber-950 shadow-amber-900/10 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-50 dark:shadow-black/40',
        icon: 'text-amber-600 dark:text-amber-400',
        path: 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
    },
    info: {
        wrap: 'border-blue-200 bg-blue-50 text-blue-950 shadow-blue-900/10 dark:border-blue-700 dark:bg-blue-950 dark:text-blue-50 dark:shadow-black/40',
        icon: 'text-blue-600 dark:text-blue-400',
        path: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
};
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed right-4 top-4 z-[200] flex w-full max-w-sm flex-col gap-3">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-x-8 opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-8 opacity-0"
                move-class="transition duration-300 ease-out"
            >
                <div
                    v-for="item in toast.items"
                    :key="item.id"
                    class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg backdrop-blur-sm"
                    :class="styles[item.type]?.wrap ?? styles.info.wrap"
                    role="status"
                >
                    <svg class="mt-0.5 h-5 w-5 shrink-0" :class="styles[item.type]?.icon ?? styles.info.icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="styles[item.type]?.path ?? styles.info.path" />
                    </svg>
                    <p class="flex-1 text-sm font-medium leading-relaxed">{{ item.message }}</p>
                    <button
                        type="button"
                        class="shrink-0 rounded p-0.5 text-current opacity-70 transition hover:opacity-100"
                        :aria-label="$t('components.dismiss')"
                        @click="toast.dismiss(item.id)"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
