<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ensureLocaleMessages, syncDocumentLocale } from '../plugins/i18n.js';

const props = defineProps({
    persist: {
        type: String,
        default: 'auto',
    },
    compact: {
        type: Boolean,
        default: true,
    },
});

const { t, locale: i18nLocale } = useI18n();
const page = usePage();
const open = ref(false);
const root = ref(null);
const saving = ref(false);

const options = computed(() => page.props.localeOptions ?? []);

const currentLocale = computed(() => page.props.locale ?? 'en');

const currentOption = computed(() => (
    options.value.find((option) => option.code === currentLocale.value)
    ?? { code: currentLocale.value, label: currentLocale.value.toUpperCase(), rtl: false }
));

const endpoint = computed(() => {
    if (props.persist === 'cookie') {
        return '/locale';
    }

    if (props.persist === 'api') {
        return '/settings/locale';
    }

    return page.props.auth?.user ? '/settings/locale' : '/locale';
});

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

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));

const setLocale = async (code) => {
    if (code === currentLocale.value || saving.value) {
        close();
        return;
    }

    const previous = currentLocale.value;
    const nextOption = options.value.find((option) => option.code === code) ?? { rtl: false };

    saving.value = true;

    try {
        await ensureLocaleMessages(code);
        i18nLocale.value = code;
        syncDocumentLocale(code, nextOption.rtl ? 'rtl' : 'ltr');
    } catch {
        saving.value = false;
        close();
        return;
    }

    router.put(endpoint.value, { locale: code }, {
        preserveScroll: true,
        onError: () => {
            i18nLocale.value = previous;
            syncDocumentLocale(previous, currentOption.value.rtl ? 'rtl' : 'ltr');
        },
        onFinish: () => {
            saving.value = false;
            close();
        },
    });
};
</script>

<template>
    <div v-if="options.length" ref="root" class="relative">
        <button
            type="button"
            class="inline-flex items-center gap-1 rounded-lg p-2 agent-text-subtle transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            :aria-label="t('components.language')"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 21a9 9 0 100-18 9 9 0 000 18z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3.6 9h16.8M3.6 15h16.8M12 3c-2.2 2.4-3.4 5.6-3.4 9s1.2 6.6 3.4 9c2.2-2.4 3.4-5.6 3.4-9S14.2 5.4 12 3z" />
            </svg>
            <span
                v-if="compact"
                class="hidden text-xs font-semibold uppercase tracking-wide lg:inline"
            >
                {{ currentOption.code }}
            </span>
        </button>

        <Transition name="dropdown">
            <div
                v-if="open"
                class="absolute right-0 z-50 mt-2 w-44 overflow-hidden rounded-xl border agent-border agent-panel py-1 shadow-lg"
                role="menu"
            >
                <p class="px-3 py-2 text-[11px] font-semibold uppercase tracking-wide agent-text-subtle">
                    {{ t('components.language') }}
                </p>
                <button
                    v-for="option in options"
                    :key="option.code"
                    type="button"
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition agent-hover-surface"
                    :class="currentLocale === option.code ? 'font-semibold text-blue-600 dark:text-blue-400' : 'agent-text-muted'"
                    role="menuitem"
                    :disabled="saving"
                    @click="setLocale(option.code)"
                >
                    <span>{{ option.label }}</span>
                    <svg v-if="currentLocale === option.code" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
            </div>
        </Transition>
    </div>
</template>
