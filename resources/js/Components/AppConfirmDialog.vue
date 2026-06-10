<script setup>
import AppModal from './AppModal.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    state: { type: Object, default: null },
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: '' },
    cancelLabel: { type: String, default: '' },
    variant: { type: String, default: 'danger' },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const { t } = useI18n();

const isOpen = computed(() => props.state?.open ?? props.open);
const resolvedTitle = computed(() => props.state?.title || props.title || t('components.confirm'));
const resolvedMessage = computed(() => props.state?.message || props.message);
const resolvedConfirmLabel = computed(() => props.state?.confirmLabel || props.confirmLabel || t('components.confirm'));
const resolvedCancelLabel = computed(() => props.cancelLabel || t('components.cancel'));
const resolvedVariant = computed(() => props.state?.variant || props.variant);
const resolvedLoading = computed(() => props.state?.loading ?? props.loading);
</script>

<template>
    <AppModal
        :open="isOpen"
        :title="resolvedTitle"
        size="sm"
        @close="emit('close')"
    >
        <p class="text-sm leading-relaxed text-slate-600">{{ resolvedMessage }}</p>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white"
                    :disabled="resolvedLoading"
                    @click="emit('close')"
                >
                    {{ resolvedCancelLabel }}
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-white transition disabled:opacity-60"
                    :class="resolvedVariant === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
                    :disabled="resolvedLoading"
                    @click="emit('confirm')"
                >
                    {{ resolvedConfirmLabel }}
                </button>
            </div>
        </template>
    </AppModal>
</template>
