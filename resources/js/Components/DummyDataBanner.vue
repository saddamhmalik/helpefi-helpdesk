<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const page = usePage();
const { t } = useI18n();

const confirming = ref(false);

const dummyData = computed(() => page.props.dummyData ?? null);
const isAdmin = computed(() => page.props.auth?.user?.is_admin ?? false);

const showBanner = computed(() => isAdmin.value && dummyData.value?.active === true);

const summaryLabel = computed(() => {
    const summary = dummyData.value?.summary ?? {};

    if (summary.tickets || summary.contacts || summary.teams) {
        return t('components.dummy_data_summary', {
            tickets: summary.tickets ?? 0,
            customers: summary.contacts ?? 0,
            teams: summary.teams ?? 0,
        });
    }

    return t('components.dummy_data_default_summary');
});

const message = computed(() => `${summaryLabel.value}${t('components.dummy_data_remove_hint')}`);

const remove = () => {
    if (!confirming.value) {
        confirming.value = true;
        return;
    }

    router.delete('/setup/dummy-data', {
        preserveScroll: true,
        onFinish: () => {
            confirming.value = false;
        },
    });
};

const cancel = () => {
    confirming.value = false;
};
</script>

<template>
    <div
        v-if="showBanner"
        class="mb-2 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2"
        role="status"
        :title="message"
    >
        <span class="shrink-0 text-sm font-semibold text-amber-950">{{ $t('components.sample_data_is_active_for_testing') }}</span>
        <span class="hidden min-w-0 flex-1 truncate text-sm text-amber-800 sm:inline">{{ message }}</span>
        <div class="ml-auto flex shrink-0 items-center gap-1.5">
            <button
                v-if="confirming"
                type="button"
                class="rounded-md px-2 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100"
                @click="cancel"
            >
                {{ $t('components.cancel') }}
            </button>
            <button
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-semibold transition"
                :class="confirming ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-amber-900 text-white hover:bg-amber-950'"
                @click="remove"
            >
                {{ confirming ? $t('components.yes_remove') : $t('components.remove_sample_data') }}
            </button>
        </div>
    </div>
</template>
