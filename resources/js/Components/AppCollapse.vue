<script setup>
defineProps({
    open: { type: Boolean, default: false },
});
</script>

<template>
    <div class="collapse-grid" :class="{ 'collapse-grid--open': open }">
        <div class="collapse-grid-inner">
            <slot />
        </div>
    </div>
</template>
