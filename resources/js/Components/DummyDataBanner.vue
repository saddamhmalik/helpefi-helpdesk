<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const page = usePage();
const { t } = useI18n();

const confirmingSample = ref(false);
const confirmingBootstrap = ref(false);
const removingSample = ref(false);
const removingBootstrap = ref(false);

const dummyData = computed(() => page.props.dummyData ?? null);
const isAdmin = computed(() => page.props.auth?.user?.is_admin ?? false);

const showSampleBanner = computed(() => isAdmin.value && dummyData.value?.active === true);
const showBootstrapBanner = computed(() => (
    isAdmin.value
    && dummyData.value?.has_bootstrap_demo === true
    && dummyData.value?.active !== true
));

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

const sampleMessage = computed(() => `${summaryLabel.value}${t('components.dummy_data_remove_hint')}`);

const removeSample = () => {
    if (!confirmingSample.value) {
        confirmingSample.value = true;
        return;
    }

    removingSample.value = true;

    router.delete('/setup/dummy-data', {
        preserveScroll: true,
        onFinish: () => {
            confirmingSample.value = false;
            removingSample.value = false;
        },
    });
};

const removeBootstrap = () => {
    if (!confirmingBootstrap.value) {
        confirmingBootstrap.value = true;
        return;
    }

    removingBootstrap.value = true;

    router.delete('/setup/bootstrap-demo', {
        preserveScroll: true,
        onFinish: () => {
            confirmingBootstrap.value = false;
            removingBootstrap.value = false;
        },
    });
};
</script>

<template>
    <div
        v-if="showSampleBanner"
        class="mb-2 flex flex-col gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-900/60 dark:bg-amber-950/40 sm:flex-row sm:items-center"
        role="status"
        :title="sampleMessage"
    >
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">{{ $t('components.sample_data_is_active_for_testing') }}</p>
            <p class="mt-0.5 text-sm text-amber-800 dark:text-amber-200">{{ sampleMessage }}</p>
            <p class="mt-1 text-xs text-amber-900/80 dark:text-amber-200/80">{{ $t('components.sample_remove_includes_bootstrap') }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-1.5 sm:ml-auto">
            <button
                v-if="confirmingSample"
                type="button"
                class="rounded-md px-2 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100 dark:text-amber-200 dark:hover:bg-amber-900/40"
                @click="confirmingSample = false"
            >
                {{ $t('components.cancel') }}
            </button>
            <button
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-semibold transition disabled:opacity-60"
                :class="confirmingSample ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-amber-900 text-white hover:bg-amber-950'"
                :disabled="removingSample"
                @click="removeSample"
            >
                {{ removingSample ? $t('components.removing') : (confirmingSample ? $t('components.yes_remove') : $t('components.remove_sample_data')) }}
            </button>
        </div>
    </div>

    <div
        v-if="showBootstrapBanner"
        class="mb-2 flex flex-col gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900 sm:flex-row sm:items-center"
        role="status"
    >
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('components.bootstrap_demo_title') }}</p>
            <p class="mt-0.5 text-sm text-slate-700 dark:text-slate-300">{{ $t('components.bootstrap_demo_present') }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('components.bootstrap_demo_keeps_admin') }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-1.5 sm:ml-auto">
            <button
                v-if="confirmingBootstrap"
                type="button"
                class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                @click="confirmingBootstrap = false"
            >
                {{ $t('components.cancel') }}
            </button>
            <button
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-semibold transition disabled:opacity-60"
                :class="confirmingBootstrap ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-slate-800 text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600'"
                :disabled="removingBootstrap"
                @click="removeBootstrap"
            >
                {{ removingBootstrap ? $t('components.removing') : (confirmingBootstrap ? $t('components.yes_remove') : $t('components.bootstrap_demo_remove')) }}
            </button>
        </div>
    </div>
</template>
