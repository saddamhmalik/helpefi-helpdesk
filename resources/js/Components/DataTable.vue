<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    empty: { type: Boolean, default: false },
    emptyTitle: { type: String, default: '' },
    emptyDescription: { type: String, default: '' },
    colspan: { type: Number, default: 1 },
});

const { t } = useI18n();

const resolvedEmptyTitle = computed(() => props.emptyTitle || t('components.no_results_found'));
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <slot />
            </table>
        </div>
        <div v-if="empty" class="px-4 py-12 text-center">
            <p class="text-sm font-medium text-slate-700">{{ resolvedEmptyTitle }}</p>
            <p v-if="emptyDescription" class="mt-1 text-sm text-slate-500">{{ emptyDescription }}</p>
            <div v-if="$slots.emptyAction" class="mt-4">
                <slot name="emptyAction" />
            </div>
        </div>
        <div v-if="$slots.footer" class="border-t border-slate-100 px-4 py-3">
            <slot name="footer" />
        </div>
    </div>
</template>
