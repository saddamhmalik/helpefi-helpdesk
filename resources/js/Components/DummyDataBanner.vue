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
        class="flex items-center gap-2 rounded-md border border-amber-200/70 bg-amber-50/80 px-2 py-1 text-xs dark:border-amber-900/40 dark:bg-amber-950/30"
        role="status"
        :title="`${summaryLabel}${t('components.dummy_data_remove_hint')} ${t('components.sample_remove_includes_bootstrap')}`"
    >
        <span class="shrink-0 rounded bg-amber-600 px-1.5 py-px text-[10px] font-bold uppercase leading-none tracking-wide text-white">
            {{ $t('components.demo_badge') }}
        </span>
        <span class="min-w-0 flex-1 truncate text-amber-950 dark:text-amber-100">{{ summaryLabel }}</span>
        <div class="flex shrink-0 items-center gap-1">
            <template v-if="confirmingSample">
                <span class="hidden text-amber-800 sm:inline dark:text-amber-200">{{ $t('components.confirm_remove') }}</span>
                <button
                    type="button"
                    class="rounded px-1.5 py-px font-medium text-amber-900 hover:bg-amber-100 dark:text-amber-200 dark:hover:bg-amber-900/40"
                    @click="confirmingSample = false"
                >
                    {{ $t('components.cancel') }}
                </button>
                <button
                    type="button"
                    class="rounded bg-red-600 px-1.5 py-px font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                    :disabled="removingSample"
                    @click="removeSample"
                >
                    {{ removingSample ? $t('components.removing') : $t('components.yes_remove') }}
                </button>
            </template>
            <button
                v-else
                type="button"
                class="rounded px-1.5 py-px font-semibold text-amber-900 underline-offset-2 hover:underline dark:text-amber-100"
                @click="removeSample"
            >
                {{ $t('components.remove') }}
            </button>
        </div>
    </div>

    <div
        v-if="showBootstrapBanner"
        class="flex items-center gap-2 rounded-md border border-slate-200/80 bg-slate-50/80 px-2 py-1 text-xs dark:border-slate-700/60 dark:bg-slate-900/60"
        role="status"
        :title="t('components.bootstrap_demo_keeps_admin')"
    >
        <span class="shrink-0 rounded bg-slate-600 px-1.5 py-px text-[10px] font-bold uppercase leading-none tracking-wide text-white dark:bg-slate-500">
            {{ $t('components.demo_badge') }}
        </span>
        <span class="min-w-0 flex-1 truncate text-slate-800 dark:text-slate-200">{{ $t('components.bootstrap_demo_present') }}</span>
        <div class="flex shrink-0 items-center gap-1">
            <template v-if="confirmingBootstrap">
                <button
                    type="button"
                    class="rounded px-1.5 py-px font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    @click="confirmingBootstrap = false"
                >
                    {{ $t('components.cancel') }}
                </button>
                <button
                    type="button"
                    class="rounded bg-red-600 px-1.5 py-px font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                    :disabled="removingBootstrap"
                    @click="removeBootstrap"
                >
                    {{ removingBootstrap ? $t('components.removing') : $t('components.yes_remove') }}
                </button>
            </template>
            <button
                v-else
                type="button"
                class="rounded px-1.5 py-px font-semibold text-slate-800 underline-offset-2 hover:underline dark:text-slate-200"
                @click="removeBootstrap"
            >
                {{ $t('components.remove') }}
            </button>
        </div>
    </div>
</template>
