<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useFixedPopover } from '../composables/useFixedPopover.js';

const props = defineProps({
    section: { type: String, required: true },
});

const { t, te, tm, locale } = useI18n();
const page = usePage();

const open = ref(false);
const anchorRef = ref(null);
const { panelStyle } = useFixedPopover(open, anchorRef);

const translationKey = (suffix) => `settings.section_info.${props.section}.${suffix}`;

const hasLocaleTranslation = (suffix, code) => te(translationKey(suffix), code);

const hasContent = computed(() => (
    hasLocaleTranslation('body', locale.value) || hasLocaleTranslation('body', 'en')
));

const title = computed(() => {
    if (hasLocaleTranslation('title', locale.value)) {
        return t(translationKey('title'));
    }

    if (hasLocaleTranslation('title', 'en')) {
        return t(translationKey('title'), {}, { locale: 'en' });
    }

    return t('common.about_this_page');
});

const body = computed(() => {
    if (hasLocaleTranslation('body', locale.value)) {
        return t(translationKey('body'));
    }

    return t(translationKey('body'), {}, { locale: 'en' });
});

const tips = computed(() => {
    const code = hasLocaleTranslation('tips', locale.value) ? locale.value : 'en';

    if (!hasLocaleTranslation('tips', code)) {
        return [];
    }

    const value = tm(translationKey('tips'), {}, { locale: code });

    return Array.isArray(value) ? value.filter(Boolean) : [];
});

const learnMoreHref = computed(() => {
    const code = hasLocaleTranslation('learn_more_href', locale.value) ? locale.value : 'en';

    return hasLocaleTranslation('learn_more_href', code) ? t(translationKey('learn_more_href'), {}, { locale: code }) : '';
});

const learnMoreLabel = computed(() => {
    if (hasLocaleTranslation('learn_more_label', locale.value)) {
        return t(translationKey('learn_more_label'));
    }

    if (hasLocaleTranslation('learn_more_label', 'en')) {
        return t(translationKey('learn_more_label'), {}, { locale: 'en' });
    }

    return t('common.learn_more');
});

const panelDir = computed(() => {
    const option = (page.props.localeOptions ?? []).find((entry) => entry.code === locale.value);

    return option?.rtl ? 'rtl' : 'ltr';
});

const toggle = () => {
    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

const onDocumentClick = (event) => {
    const panel = document.getElementById(`settings-section-info-panel-${props.section}`);

    if (
        open.value
        && anchorRef.value
        && !anchorRef.value.contains(event.target)
        && !panel?.contains(event.target)
    ) {
        close();
    }
};

const onDocumentKeydown = (event) => {
    if (open.value && event.key === 'Escape') {
        close();
    }
};

onMounted(() => {
    document.addEventListener('mousedown', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
});
</script>

<template>
    <Teleport v-if="hasContent" to="#settings-page-actions" defer>
        <div ref="anchorRef">
            <button
                type="button"
                class="rounded-lg p-2 agent-text-subtle transition agent-hover-surface hover:text-blue-600 dark:hover:text-blue-400"
                :aria-label="title"
                :aria-expanded="open"
                :title="title"
                @click="toggle"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </button>
        </div>
    </Teleport>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
        >
            <div
                v-if="open && hasContent"
                :id="`settings-section-info-panel-${section}`"
                :style="panelStyle"
                class="rounded-xl border agent-border bg-white p-4 shadow-lg dark:bg-slate-900"
                :dir="panelDir"
                role="dialog"
                :aria-label="title"
            >
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold agent-text">{{ title }}</p>
                    <button
                        type="button"
                        class="rounded-md p-1 agent-text-subtle transition agent-hover-surface"
                        :aria-label="t('common.close')"
                        @click="close"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <p class="mt-2 text-sm leading-relaxed agent-text-muted">{{ body }}</p>

                <ul v-if="tips.length" class="mt-3 space-y-2">
                    <li
                        v-for="(tip, index) in tips"
                        :key="index"
                        class="flex gap-2 text-sm leading-relaxed agent-text-muted"
                    >
                        <span class="shrink-0 text-slate-400 dark:text-slate-500" aria-hidden="true">•</span>
                        <span>{{ tip }}</span>
                    </li>
                </ul>

                <a
                    v-if="learnMoreHref"
                    :href="learnMoreHref"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-flex text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                >
                    {{ learnMoreLabel }}
                </a>
            </div>
        </Transition>
    </Teleport>
</template>
