<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    applyAppearance,
    isDarkAppearance,
    readStoredAppearance,
    writeStoredAppearance,
} from '../composables/useAppearance.js';

const props = defineProps({
    persist: {
        type: String,
        default: 'auto',
    },
});

const { t } = useI18n();
const page = usePage();
const open = ref(false);
const root = ref(null);
const saving = ref(false);
const localAppearance = ref('system');

const options = [
    { value: 'light', labelKey: 'profile.appearance_light' },
    { value: 'dark', labelKey: 'profile.appearance_dark' },
    { value: 'system', labelKey: 'profile.appearance_system' },
];

const usesApi = computed(() => {
    if (props.persist === 'api') {
        return !!page.props.auth?.user;
    }

    if (props.persist === 'local') {
        return false;
    }

    return !!page.props.auth?.user;
});

const appearance = computed(() => {
    if (usesApi.value) {
        return page.props.appearance ?? page.props.auth?.user?.appearance ?? 'system';
    }

    return localAppearance.value;
});

const resolvedDark = computed(() => {
    if (typeof window === 'undefined') {
        return appearance.value === 'dark';
    }

    return isDarkAppearance(appearance.value);
});

const syncLocalAppearance = () => {
    const fromPage = page.props.appearance ?? page.props.auth?.user?.appearance;
    const stored = readStoredAppearance(page.props.tenantId, page.props.auth?.user?.id);

    localAppearance.value = stored ?? fromPage ?? 'system';
};

const toggle = () => {
    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

const onDocumentClick = (event) => {
    if (!root.value?.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    syncLocalAppearance();
});

watch(
    () => [page.props.appearance, page.props.auth?.user?.appearance, page.props.tenantId, page.props.auth?.user?.id],
    syncLocalAppearance,
);

onUnmounted(() => document.removeEventListener('click', onDocumentClick));

const setAppearance = (value) => {
    if (value === appearance.value || saving.value) {
        close();
        return;
    }

    const previous = appearance.value;

    saving.value = true;
    applyAppearance(value);

    if (usesApi.value) {
        router.put('/settings/appearance', { appearance: value }, {
            preserveScroll: true,
            onError: () => {
                applyAppearance(previous);
            },
            onFinish: () => {
                saving.value = false;
                close();
            },
        });

        return;
    }

    writeStoredAppearance(page.props.tenantId, page.props.auth?.user?.id, value);
    localAppearance.value = value;
    saving.value = false;
    close();
};
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="rounded-lg p-2 agent-text-subtle transition hover:bg-slate-100 dark:bg-slate-900 hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            :aria-label="t('components.theme')"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            <svg v-if="appearance === 'system'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <svg v-else-if="resolvedDark" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
        </button>

        <Transition name="dropdown">
            <div
                v-if="open"
                class="absolute right-0 z-50 mt-2 w-44 overflow-hidden rounded-xl border agent-border agent-panel py-1 shadow-lg"
                role="menu"
            >
                <p class="px-3 py-2 text-[11px] font-semibold uppercase tracking-wide agent-text-subtle">
                    {{ t('components.theme') }}
                </p>
                <button
                    v-for="option in options"
                    :key="option.value"
                    type="button"
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition agent-hover-surface"
                    :class="appearance === option.value ? 'font-semibold text-blue-600 dark:text-blue-400' : 'agent-text-muted'"
                    role="menuitem"
                    :disabled="saving"
                    @click="setAppearance(option.value)"
                >
                    {{ t(option.labelKey) }}
                    <svg v-if="appearance === option.value" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
            </div>
        </Transition>
    </div>
</template>
