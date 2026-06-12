<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBodyScrollLock, useEscapeKey } from '../composables/useModal.js';

const props = defineProps({
    open: { type: Boolean, default: false },
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    size: { type: String, default: 'lg' },
    variant: { type: String, default: 'center' },
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const { t } = useI18n();

const isOpen = computed(() => props.open || props.show);

useBodyScrollLock(isOpen);
useEscapeKey(isOpen, () => emit('close'));

const sizeClass = computed(() => ({
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
}[props.size] ?? 'max-w-2xl'));

const dialogAriaLabel = computed(() => props.title || t('components.dialog'));

const onBackdropClick = () => {
    if (props.closeOnBackdrop) {
        emit('close');
    }
};
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 flex"
                :class="variant === 'drawer' ? 'justify-end' : 'items-center justify-center p-4'"
                role="dialog"
                aria-modal="true"
                :aria-label="dialogAriaLabel"
            >
                <div
                    class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px] transition-opacity"
                    @click="onBackdropClick"
                />

                <Transition :name="variant === 'drawer' ? 'modal-drawer' : 'modal-scale'" appear>
                    <div
                        v-if="isOpen"
                        class="relative flex max-h-[92vh] w-full flex-col overflow-hidden agent-panel shadow-2xl"
                        :class="[
                            variant === 'drawer'
                                ? 'h-full max-w-2xl rounded-l-2xl border-l agent-border'
                                : `rounded-2xl border agent-border ${sizeClass}`,
                        ]"
                        @click.stop
                    >
                        <div class="flex items-start justify-between gap-4 border-b agent-border-subtle px-6 py-4">
                            <div class="min-w-0">
                                <h2 v-if="title" class="text-lg font-semibold agent-text">{{ title }}</h2>
                                <p v-if="description" class="mt-1 text-sm agent-text-subtle">{{ description }}</p>
                                <slot name="header" />
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-lg p-2 text-slate-400 dark:text-slate-500 transition agent-hover-surface hover:text-slate-600 dark:text-slate-400 dark:hover:text-slate-200"
                                :aria-label="$t('components.close')"
                                @click="emit('close')"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto px-6 py-5">
                            <slot />
                        </div>

                        <div v-if="$slots.footer" class="border-t agent-border-subtle agent-panel-muted px-6 py-4">
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.modal-scale-enter-active {
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}

.modal-scale-leave-active {
    transition: transform 0.18s ease, opacity 0.15s ease;
}

.modal-scale-enter-from {
    opacity: 0;
    transform: scale(0.96) translateY(10px);
}

.modal-scale-leave-to {
    opacity: 0;
    transform: scale(0.98) translateY(4px);
}

.modal-drawer-enter-active {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.modal-drawer-leave-active {
    transition: transform 0.22s ease;
}

.modal-drawer-enter-from,
.modal-drawer-leave-to {
    transform: translateX(100%);
}
</style>
