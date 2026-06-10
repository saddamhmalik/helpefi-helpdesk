<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    icon: { type: String, default: 'edit' },
    variant: { type: String, default: 'primary' },
    href: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
});

const emit = defineEmits(['click']);

const buttonClass = computed(() => {
    const base = 'inline-flex items-center justify-center rounded-lg p-2 transition disabled:cursor-not-allowed disabled:opacity-40';
    const variants = {
        neutral: 'text-slate-400 hover:bg-slate-100 hover:text-slate-700',
        primary: 'text-slate-400 hover:bg-blue-50 hover:text-blue-600',
        danger: 'text-slate-400 hover:bg-red-50 hover:text-red-600',
        violet: 'text-slate-400 hover:bg-violet-50 hover:text-violet-600',
    };

    return `${base} ${variants[props.variant] ?? variants.primary}`;
});

const iconPaths = computed(() => {
    const paths = {
        edit: ['M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
        delete: ['M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'],
        view: [
            'M15 12a3 3 0 11-6 0 3 3 0 016 0z',
            'M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
        ],
    };

    return paths[props.icon] ?? paths.edit;
});

const onClick = (event) => {
    event.stopPropagation();

    if (!props.disabled) {
        emit('click', event);
    }
};
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :class="buttonClass"
        :title="label"
        :aria-label="label"
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path v-for="(path, index) in iconPaths" :key="index" stroke-linecap="round" stroke-linejoin="round" :d="path" />
        </svg>
    </Link>
    <button
        v-else
        :type="type"
        :class="buttonClass"
        :title="label"
        :aria-label="label"
        :disabled="disabled"
        @click="onClick"
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path v-for="(path, index) in iconPaths" :key="index" stroke-linecap="round" stroke-linejoin="round" :d="path" />
        </svg>
    </button>
</template>
