<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, useAttrs } from 'vue';
import AppSpinner from './AppSpinner.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'ghost', 'danger'].includes(value),
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['xs', 'sm', 'md', 'lg'].includes(value),
    },
    type: {
        type: String,
        default: 'button',
    },
    href: { type: String, default: '' },
    external: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const attrs = useAttrs();

const isDisabled = computed(() => props.disabled || props.loading);

const sizeClass = computed(() => ({
    xs: 'gap-1 rounded-lg px-2.5 py-1.5 text-xs',
    sm: 'gap-1.5 rounded-lg px-3 py-2 text-sm',
    md: 'gap-2 rounded-xl px-4 py-2.5 text-sm',
    lg: 'gap-2 rounded-xl px-5 py-3 text-base',
}[props.size] ?? 'gap-2 rounded-xl px-4 py-2.5 text-sm'));

const variantClass = computed(() => {
    const base = 'inline-flex items-center justify-center font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950';

    const variants = {
        primary: `${base} bg-blue-600 text-white shadow-sm shadow-blue-600/20 hover:bg-blue-700 focus-visible:ring-blue-500 disabled:bg-blue-600`,
        secondary: `${base} agent-btn-secondary focus-visible:ring-slate-400`,
        ghost: `${base} text-slate-700 hover:bg-slate-100 focus-visible:ring-slate-400 dark:text-slate-300 dark:hover:bg-slate-800`,
        danger: `${base} bg-red-600 text-white shadow-sm hover:bg-red-700 focus-visible:ring-red-500 disabled:bg-red-600`,
    };

    return variants[props.variant] ?? variants.primary;
});

const classes = computed(() => [
    variantClass.value,
    sizeClass.value,
    props.block ? 'w-full' : '',
    isDisabled.value ? 'cursor-not-allowed opacity-60' : '',
]);

const spinnerSize = computed(() => ({
    xs: 'xs',
    sm: 'xs',
    md: 'sm',
    lg: 'sm',
}[props.size] ?? 'sm'));

const componentTag = computed(() => {
    if (props.href && props.external) {
        return 'a';
    }

    if (props.href) {
        return Link;
    }

    return 'button';
});

const componentProps = computed(() => {
    if (props.href && props.external) {
        return {
            href: props.href,
            target: '_blank',
            rel: 'noopener noreferrer',
            'aria-disabled': isDisabled.value ? 'true' : undefined,
            class: classes.value,
            ...attrs,
        };
    }

    if (props.href) {
        return {
            href: isDisabled.value ? undefined : props.href,
            as: isDisabled.value ? 'button' : undefined,
            type: isDisabled.value ? 'button' : undefined,
            disabled: isDisabled.value ? true : undefined,
            'aria-disabled': isDisabled.value ? 'true' : undefined,
            class: classes.value,
            ...attrs,
        };
    }

    return {
        type: props.type,
        disabled: isDisabled.value,
        class: classes.value,
        ...attrs,
    };
});
</script>

<template>
    <component :is="componentTag" v-bind="componentProps">
        <AppSpinner v-if="loading" :size="spinnerSize" inline />
        <span v-if="$slots.leading && !loading" class="inline-flex shrink-0">
            <slot name="leading" />
        </span>
        <span class="truncate">
            <slot />
        </span>
        <span v-if="$slots.trailing && !loading" class="inline-flex shrink-0">
            <slot name="trailing" />
        </span>
    </component>
</template>
