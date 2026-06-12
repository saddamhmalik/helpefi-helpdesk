<script setup>
defineProps({
    size: {
        type: String,
        default: 'md',
    },
    markOnly: {
        type: Boolean,
        default: false,
    },
    surface: {
        type: String,
        default: 'none',
    },
});

const sizeClasses = {
    sm: 'h-7',
    md: 'h-8',
    lg: 'h-9',
    xl: 'h-10',
};

const imageClass = (size, markOnly) => [
    sizeClasses[size] ?? sizeClasses.md,
    markOnly ? 'w-auto aspect-square' : 'w-auto',
    'shrink-0 object-contain',
];
</script>

<template>
    <span
        v-if="surface === 'light'"
        class="inline-flex items-center rounded-xl bg-white dark:bg-slate-900 px-3 py-1.5 shadow-sm shadow-black/10 ring-1 ring-black/5"
    >
        <img
            :src="markOnly ? '/icon.png' : '/logo.png'"
            alt="helpefi"
            :class="imageClass(size, markOnly)"
        />
    </span>
    <img
        v-else
        :src="markOnly ? '/icon.png' : '/logo.png'"
        alt="helpefi"
        :class="imageClass(size, markOnly)"
    />
</template>
