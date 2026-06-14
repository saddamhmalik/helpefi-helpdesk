<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    modelValue: { type: String, required: true },
    items: { type: Array, required: true },
    variant: { type: String, default: 'underline' },
});

const emit = defineEmits(['update:modelValue']);
const slots = useSlots();

const select = (id) => {
    emit('update:modelValue', id);
};

const buttonClass = (active, variant) => {
    if (variant === 'pills') {
        return active
            ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-100 dark:text-slate-900'
            : 'agent-text-muted agent-hover-surface hover:text-slate-900 dark:hover:text-slate-100';
    }

    return active
        ? 'border-blue-600 text-blue-600 dark:text-blue-400'
        : 'border-transparent agent-text-subtle hover:border-slate-300 dark:border-slate-700 hover:text-slate-700 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-slate-300';
};

const hasPanels = computed(() => props.items.some((item) => Boolean(slots[item.id])));
</script>

<template>
    <div>
        <div
            :class="[
                hasPanels ? 'mb-6' : 'mb-4',
                variant === 'pills'
                    ? 'inline-flex rounded-xl border agent-border agent-panel p-1 shadow-sm'
                    : 'border-b agent-border',
            ]"
        >
            <nav
                class="flex gap-1 overflow-x-auto"
                :class="variant === 'pills' ? '' : '-mb-px'"
                :aria-label="$t('components.tabs')"
            >
                <button
                    v-for="item in items"
                    :key="item.id"
                    type="button"
                    class="whitespace-nowrap text-sm font-medium transition"
                    :class="[
                        variant === 'pills'
                            ? 'rounded-lg px-3 py-1.5'
                            : 'border-b-2 px-4 py-2.5',
                        buttonClass(modelValue === item.id, variant),
                    ]"
                    :disabled="item.disabled"
                    @click="select(item.id)"
                >
                    {{ item.label }}
                    <span
                        v-if="item.badge != null"
                        class="ml-1.5 rounded-full px-1.5 py-0.5 text-xs tabular-nums"
                        :class="modelValue === item.id
                            ? (variant === 'pills' ? 'bg-white/15 text-white dark:bg-slate-900/20' : 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300')
                            : 'agent-panel-muted agent-text-subtle'"
                    >
                        {{ item.badge }}
                    </span>
                </button>
            </nav>
        </div>

        <div v-if="hasPanels">
            <template v-for="item in items" :key="`panel-${item.id}`">
                <div v-show="modelValue === item.id">
                    <slot :name="item.id" />
                </div>
            </template>
        </div>
    </div>
</template>
