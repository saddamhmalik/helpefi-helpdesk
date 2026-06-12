<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formMaxWidthClass } from '../composables/useFormControls.js';

const props = defineProps({
    description: { type: String, default: '' },
    cancelHref: { type: String, default: '' },
    cancelLabel: { type: String, default: '' },
    submitLabel: { type: String, default: '' },
    processing: { type: Boolean, default: false },
    submitDisabled: { type: Boolean, default: false },
    maxWidth: { type: String, default: 'lg' },
});

defineEmits(['submit']);

const { t } = useI18n();

const widthClass = computed(() => formMaxWidthClass(props.maxWidth));
const resolvedCancelLabel = computed(() => props.cancelLabel || t('components.cancel'));
const resolvedSubmitLabel = computed(() => props.submitLabel || t('components.save'));
</script>

<template>
    <div class="mx-auto w-full" :class="widthClass">
        <div class="overflow-hidden rounded-2xl border agent-border agent-panel shadow-sm shadow-slate-200/60 dark:shadow-slate-900/40">
            <div v-if="description || $slots.header" class="border-b agent-border-subtle agent-panel-muted px-6 py-5 sm:px-8">
                <slot name="header">
                    <p v-if="description" class="text-sm leading-relaxed agent-text-subtle">{{ description }}</p>
                </slot>
            </div>

            <form @submit.prevent="$emit('submit')">
                <div class="px-6 py-6 sm:px-8 sm:py-8">
                    <slot />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t agent-border-subtle agent-panel-muted px-6 py-4 sm:px-8">
                    <Link
                        v-if="cancelHref"
                        :href="cancelHref"
                        class="text-sm font-medium agent-text-muted transition hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100"
                    >
                        {{ resolvedCancelLabel }}
                    </Link>
                    <span v-else />

                    <div class="flex items-center gap-2">
                        <slot name="actions" />
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="processing || submitDisabled"
                        >
                            {{ processing ? $t('components.saving') : resolvedSubmitLabel }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
