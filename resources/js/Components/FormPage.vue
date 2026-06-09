<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formMaxWidthClass } from '../composables/useFormControls.js';

const props = defineProps({
    description: { type: String, default: '' },
    cancelHref: { type: String, default: '' },
    cancelLabel: { type: String, default: 'Cancel' },
    submitLabel: { type: String, default: 'Save' },
    processing: { type: Boolean, default: false },
    maxWidth: { type: String, default: 'lg' },
});

defineEmits(['submit']);

const widthClass = computed(() => formMaxWidthClass(props.maxWidth));
</script>

<template>
    <div class="mx-auto w-full" :class="widthClass">
        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm shadow-slate-200/60">
            <div v-if="description || $slots.header" class="border-b border-slate-100 bg-slate-50/40 px-6 py-5 sm:px-8">
                <slot name="header">
                    <p v-if="description" class="text-sm leading-relaxed text-slate-500">{{ description }}</p>
                </slot>
            </div>

            <form @submit.prevent="$emit('submit')">
                <div class="px-6 py-6 sm:px-8 sm:py-8">
                    <slot />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/70 px-6 py-4 sm:px-8">
                    <Link
                        v-if="cancelHref"
                        :href="cancelHref"
                        class="text-sm font-medium text-slate-600 transition hover:text-slate-900"
                    >
                        {{ cancelLabel }}
                    </Link>
                    <span v-else />

                    <div class="flex items-center gap-2">
                        <slot name="actions" />
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="processing"
                        >
                            {{ processing ? 'Saving…' : submitLabel }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
