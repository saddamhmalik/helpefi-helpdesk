<script setup>
import { computed, ref, watch } from 'vue';
import { avatarColor, avatarLabel } from './ticketMessage.js';

const props = defineProps({
    name: { type: String, default: '' },
    email: { type: String, default: '' },
    imageUrl: { type: String, default: null },
    size: { type: String, default: 'md' },
});

const label = computed(() => avatarLabel(props.name, props.email));
const color = computed(() => avatarColor(props.name || props.email || '?'));
const showImage = ref(Boolean(props.imageUrl));

const sizeClass = computed(() => ({
    sm: 'h-7 w-7 text-[10px]',
    md: 'h-9 w-9 text-sm',
    lg: 'h-11 w-11 text-base',
}[props.size] ?? 'h-9 w-9 text-sm'));

const onImageError = () => {
    showImage.value = false;
};

watch(
    () => props.imageUrl,
    (value) => {
        showImage.value = Boolean(value);
    },
);
</script>

<template>
    <span
        class="relative inline-flex shrink-0 overflow-hidden rounded-full shadow-sm ring-2 ring-white"
        :class="sizeClass"
        :title="name || email"
    >
        <img
            v-if="imageUrl && showImage"
            :src="imageUrl"
            :alt="name || email"
            class="h-full w-full object-cover"
            @error="onImageError"
        />
        <span
            v-else
            class="inline-flex h-full w-full items-center justify-center font-semibold text-white"
            :style="{ backgroundColor: color }"
        >
            {{ label }}
        </span>
    </span>
</template>
