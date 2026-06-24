<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useFixedPopover } from '../../composables/useFixedPopover.js';

const props = defineProps({
    align: {
        type: String,
        default: 'start',
        validator: (value) => ['start', 'end'].includes(value),
    },
    minWidth: { type: String, default: '12rem' },
});

const emit = defineEmits(['open', 'close']);

const open = ref(false);
const triggerRef = ref(null);
const panelRef = ref(null);
const activeIndex = ref(-1);

const { panelStyle, updatePosition } = useFixedPopover(open, triggerRef, panelRef);

const panelClasses = computed(() => [
    'rounded-xl border agent-border agent-panel py-1 shadow-lg',
    props.align === 'end' ? 'origin-top-right' : 'origin-top-left',
]);

const close = () => {
    open.value = false;
};

const toggle = () => {
    open.value = !open.value;
};

watch(open, (isOpen) => {
    if (isOpen) {
        activeIndex.value = -1;
        emit('open');
        nextTick(updatePosition);

        return;
    }

    emit('close');
});

const onDocumentClick = (event) => {
    if (!open.value) {
        return;
    }

    const target = event.target;

    if (triggerRef.value?.contains(target) || panelRef.value?.contains(target)) {
        return;
    }

    close();
};

const menuItems = () => Array.from(panelRef.value?.querySelectorAll('[role="menuitem"]:not([disabled])') ?? []);

const focusItem = (index) => {
    const items = menuItems();
    const item = items[index];

    if (item) {
        activeIndex.value = index;
        item.focus();
    }
};

const onTriggerKeydown = (event) => {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();

        if (!open.value) {
            open.value = true;
            nextTick(() => focusItem(0));

            return;
        }

        focusItem(0);
    }

    if (event.key === 'Escape') {
        close();
    }
};

const onPanelKeydown = (event) => {
    const items = menuItems();

    if (!items.length) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        triggerRef.value?.focus();

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        const next = activeIndex.value < items.length - 1 ? activeIndex.value + 1 : 0;
        focusItem(next);

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        const previous = activeIndex.value > 0 ? activeIndex.value - 1 : items.length - 1;
        focusItem(previous);

        return;
    }

    if (event.key === 'Home') {
        event.preventDefault();
        focusItem(0);

        return;
    }

    if (event.key === 'End') {
        event.preventDefault();
        focusItem(items.length - 1);
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});

defineExpose({ close, open, toggle });
</script>

<template>
    <div class="relative inline-flex">
        <div
            ref="triggerRef"
            @keydown="onTriggerKeydown"
        >
            <slot name="trigger" :open="open" :toggle="toggle" :close="close" />
        </div>

        <Teleport to="body">
            <Transition name="dropdown">
                <div
                    v-if="open"
                    ref="panelRef"
                    role="menu"
                    :style="{ ...panelStyle, minWidth }"
                    :class="panelClasses"
                    @keydown="onPanelKeydown"
                >
                    <slot :close="close" />
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
