<script setup>
import AppModal from './AppModal.vue';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    variant: { type: String, default: 'danger' },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <AppModal
        :open="open"
        :title="title"
        size="sm"
        @close="emit('close')"
    >
        <p class="text-sm leading-relaxed text-slate-600">{{ message }}</p>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white"
                    :disabled="loading"
                    @click="emit('close')"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-white transition disabled:opacity-60"
                    :class="variant === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
                    :disabled="loading"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </template>
    </AppModal>
</template>
