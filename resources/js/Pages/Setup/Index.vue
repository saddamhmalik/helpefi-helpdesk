<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import { useClipboard } from '../../composables/useClipboard.js';
import { useToast } from '../../composables/useToast.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    guide: Object,
    welcome: Boolean,
    dummyData: {
        type: Object,
        default: () => ({ active: false, needs_choice: true, summary: {} }),
    },
});

const { t } = useI18n();

const loadingSample = ref(false);
const loadingSkip = ref(false);
const confirmingRemove = ref(false);
const confirmingBootstrapRemove = ref(false);
const removingSample = ref(false);
const removingBootstrap = ref(false);

const showWelcome = ref(props.welcome);
const { copied: snippetCopied, copy: copySnippet } = useClipboard();
const toast = useToast();
const progress = computed(() => props.guide?.progress ?? { completed: 0, total: 0 });
const guidePaused = computed(() => props.dummyData?.active === true);
const showDemoLoadSample = computed(() => props.dummyData?.can_load_sample === true);
const showDemoBootstrap = computed(() => props.dummyData?.has_bootstrap_demo === true);
const showDemoManagement = computed(() => (
    !guidePaused.value
    && !props.dummyData?.needs_choice
    && (showDemoLoadSample.value || showDemoBootstrap.value)
));

const sampleSummaryLabel = computed(() => t('setup_index.sample_summary', {
    tickets: props.dummyData?.summary?.tickets ?? 0,
    contacts: props.dummyData?.summary?.contacts ?? 0,
    teams: props.dummyData?.summary?.teams ?? 0,
    departments: props.dummyData?.summary?.departments ?? 0,
}));

const canFinish = computed(() => (
    !guidePaused.value
    && !props.dummyData?.needs_choice
    && progress.value.completed >= progress.value.total
    && progress.value.total > 0
));

const loadSampleData = () => {
    loadingSample.value = true;
    router.post('/setup/dummy-data', {}, {
        preserveScroll: true,
        onFinish: () => {
            loadingSample.value = false;
        },
    });
};

const startEmpty = () => {
    loadingSkip.value = true;
    router.post('/setup/dummy-data/skip', {}, {
        preserveScroll: true,
        onFinish: () => {
            loadingSkip.value = false;
        },
    });
};

const removeSampleData = () => {
    if (!confirmingRemove.value) {
        confirmingRemove.value = true;
        return;
    }

    removingSample.value = true;

    router.delete('/setup/dummy-data', {
        preserveScroll: true,
        onFinish: () => {
            confirmingRemove.value = false;
            removingSample.value = false;
        },
    });
};

const removeBootstrapDemo = () => {
    if (!confirmingBootstrapRemove.value) {
        confirmingBootstrapRemove.value = true;
        return;
    }

    removingBootstrap.value = true;

    router.delete('/setup/bootstrap-demo', {
        preserveScroll: true,
        onFinish: () => {
            confirmingBootstrapRemove.value = false;
            removingBootstrap.value = false;
        },
    });
};
const isDismissed = computed(() => props.guide?.completed === true);

onMounted(() => {
    if (! props.welcome) {
        return;
    }

    window.setTimeout(() => {
        showWelcome.value = false;
    }, 6000);
});

const completeStep = (key) => {
    router.post(`/setup/steps/${key}`, {}, { preserveScroll: true });
};

const finish = () => {
    router.post('/setup/finish');
};

const copy = async (text) => {
    const success = await copySnippet(text);

    if (! success) {
        toast.error(t('setup_index.could_not_copy'));
    }
};
</script>

<template>
    <Head :title="$t('setup_index.workspace_setup')" />
    <AgentLayout>
        <div class="mx-auto max-w-3xl px-4 py-8">
            <Transition name="welcome-banner">
                <div
                    v-if="showWelcome"
                    class="welcome-banner relative mb-8 overflow-hidden rounded-2xl border border-blue-200 dark:border-blue-900/60 bg-gradient-to-br from-blue-600 via-blue-600 to-indigo-600 p-6 text-white shadow-lg"
                >
                    <div class="welcome-confetti pointer-events-none absolute inset-0 overflow-hidden">
                        <span v-for="n in 12" :key="n" class="welcome-particle" :style="{ '--i': n }" />
                    </div>
                    <div class="relative flex items-start gap-4">
                        <div class="welcome-check flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/20 text-2xl font-bold backdrop-blur">
                            ✓
                        </div>
                        <div>
                            <p class="text-sm font-medium text-blue-100">{{ $t('setup_index.workspace_ready') }}</p>
                            <h2 class="mt-1 text-xl font-semibold">Welcome to {{ guide.workspace?.name }}</h2>
                            <p class="mt-2 text-sm text-blue-100">
                                {{ $t('setup_index.helpdesk_live_at') }}
                                <span class="font-medium text-white">{{ guide.workspace?.domain }}</span>.
                                {{ $t('setup_index.complete_steps_below') }}
                            </p>
                        </div>
                    </div>
                </div>
            </Transition>

            <section
                v-if="dummyData.needs_choice"
                class="mb-8 overflow-hidden rounded-2xl border border-blue-200 dark:border-blue-900/60 bg-white dark:bg-slate-900 shadow-sm"
            >
                <div class="border-b border-blue-100 bg-blue-50 px-5 py-4 dark:border-blue-900/60 dark:bg-blue-950/50">
                    <p class="text-sm font-semibold text-blue-900 dark:text-blue-100">{{ $t('setup_index.how_would_you_like_to_start') }}</p>
                    <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">
                        {{ $t('setup_index.load_realistic_sample_tickets_and_customers_to_explore_the_product_or_') }}
                    </p>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <button
                        type="button"
                        class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-left transition hover:border-blue-400 hover:bg-blue-100 disabled:opacity-60 dark:border-blue-800 dark:bg-blue-950/60 dark:hover:border-blue-600 dark:hover:bg-blue-950/80"
                        :disabled="loadingSample || loadingSkip"
                        @click="loadSampleData"
                    >
                        <p class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $t('setup_index.explore_with_sample_data') }}</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ $t('setup_index.adds_demo_tickets_conversations_customers_tags_teams_and_departments_y') }}
                        </p>
                        <p class="mt-3 text-xs font-medium text-blue-700 dark:text-blue-300">
                            {{ loadingSample ? $t('setup_index.loading_sample_data') : $t('setup_index.recommended_first_time') }}
                        </p>
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 dark:border-slate-800 p-5 text-left transition hover:border-slate-300 dark:hover:border-slate-600 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 dark:bg-slate-950 disabled:opacity-60"
                        :disabled="loadingSample || loadingSkip"
                        @click="startEmpty"
                    >
                        <p class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $t('setup_index.start_with_my_own_data') }}</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                            {{ $t('setup_index.skip_sample_content_and_build_your_workspace_from_scratch_with_real_cu') }}
                        </p>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ $t('setup_index.empty_workspace_keeps_default_demo') }}
                        </p>
                        <p class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">
                            {{ loadingSkip ? $t('setup_index.continuing') : $t('setup_index.empty_workspace') }}
                        </p>
                    </button>
                </div>
                <p class="border-t border-slate-100 dark:border-slate-800 px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ $t('setup_index.sample_data_is_for_testing_only_if_you_load_it_you_can_remove_everythi') }}
                </p>
            </section>

            <section
                v-else-if="guidePaused"
                class="mb-8 overflow-hidden rounded-2xl border border-amber-200 dark:border-amber-900/60 bg-white dark:bg-slate-900 shadow-sm"
            >
                <div class="border-b border-amber-100 bg-amber-50 dark:bg-amber-950/40 px-5 py-4">
                    <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">{{ $t('setup_index.sample_workspace_ready') }}</p>
                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">{{ sampleSummaryLabel }}</p>
                </div>
                <div class="space-y-4 p-5">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ $t('setup_index.workspace_setup_is_paused_while_you_explore_open_the_inbox_browse_cust') }}
                    </p>
                    <p class="text-xs text-amber-900/80 dark:text-amber-200/80">{{ $t('setup_index.sample_remove_includes_bootstrap') }}</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Link
                            href="/workspace"
                            class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700"
                        >
                            {{ $t('setup_index.open_inbox') }}
                        </Link>
                        <Link
                            href="/dashboard"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 text-sm font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            {{ $t('nav.dashboard') }}
                        </Link>
                    </div>
                </div>
                <div class="flex flex-col gap-3 border-t border-amber-100 bg-amber-50 dark:bg-amber-950/40/50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-amber-900 dark:text-amber-200">{{ $t('setup_index.ready_configure_real') }}</p>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="confirmingRemove"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium text-amber-900 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-900/40"
                            @click="confirmingRemove = false"
                        >{{ $t('setup_index.cancel') }}</button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-semibold text-white transition disabled:opacity-60"
                            :class="confirmingRemove ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-900 hover:bg-amber-950'"
                            :disabled="removingSample"
                            @click="removeSampleData"
                        >
                            {{ removingSample ? $t('setup_index.removing') : (confirmingRemove ? $t('setup_index.yes_remove_sample_data') : $t('setup_index.remove_sample_data')) }}
                        </button>
                    </div>
                </div>
            </section>

            <section
                v-else-if="showDemoManagement"
                class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="border-b border-slate-100 bg-slate-50 px-5 py-4 dark:border-slate-800 dark:bg-slate-950/80">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('setup_index.sample_demo_content_title') }}</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        {{ $t('setup_index.sample_demo_content_description') }}
                    </p>
                </div>
                <div class="space-y-4 p-5">
                    <div
                        v-if="showDemoLoadSample"
                        class="flex flex-col gap-3 rounded-xl border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900/60 dark:bg-blue-950/20 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('setup_index.explore_with_sample_data') }}</p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                {{ $t('setup_index.adds_demo_tickets_conversations_customers_tags_teams_and_departments_y') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
                            :disabled="loadingSample"
                            @click="loadSampleData"
                        >
                            {{ loadingSample ? $t('setup_index.loading_sample_data') : $t('setup_index.load_sample_data') }}
                        </button>
                    </div>
                    <div
                        v-if="showDemoBootstrap"
                        class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/60 dark:bg-amber-950/20 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('setup_index.default_demo_content_title') }}</p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                {{ $t('setup_index.default_demo_content_description') }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('setup_index.bootstrap_demo_keeps_admin') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                v-if="confirmingBootstrapRemove"
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:text-amber-200 dark:hover:bg-amber-900/40"
                                @click="confirmingBootstrapRemove = false"
                            >
                                {{ $t('setup_index.cancel') }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition disabled:opacity-60"
                                :class="confirmingBootstrapRemove ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-900 hover:bg-amber-950'"
                                :disabled="removingBootstrap"
                                @click="removeBootstrapDemo"
                            >
                                {{ removingBootstrap ? $t('setup_index.removing') : (confirmingBootstrapRemove ? $t('setup_index.yes_remove_demo_content') : $t('setup_index.remove_demo_content')) }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="!guidePaused" class="mb-8">
                <p class="text-sm font-medium text-blue-600">{{ $t('setup_index.getting_started') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">Set up {{ guide.workspace?.name }}</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    {{ $t('setup_index.complete_steps_configure') }}
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ guide.workspace?.domain }}</span>.
                </p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-900">
                    <div
                        class="h-full rounded-full bg-blue-600 transition-all duration-700 ease-out"
                        :style="{ width: `${progress.total ? (progress.completed / progress.total) * 100 : 0}%` }"
                    />
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $t('setup_index.steps_complete', { completed: progress.completed, total: progress.total }) }}</p>
            </div>

            <div v-if="!guidePaused" class="space-y-4">
                <article
                    v-for="(step, index) in guide.steps"
                    :key="step.key"
                    class="setup-step rounded-xl border bg-white dark:bg-slate-900 shadow-sm"
                    :class="step.complete ? 'border-emerald-200 dark:border-emerald-900/60' : 'border-slate-200 dark:border-slate-800'"
                    :style="{ animationDelay: `${index * 60}ms` }"
                >
                    <div class="p-5">
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors"
                                :class="step.complete ? 'bg-emerald-100 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-900 text-slate-500 dark:text-slate-400'"
                            >
                                {{ step.complete ? '✓' : '•' }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ step.title }}</h2>
                                    <span v-if="!step.required" class="rounded-full bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('setup_index.optional') }}</span>
                                    <span
                                        v-if="step.complete"
                                        class="rounded-full bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300"
                                    >
                                        {{ $t('setup_index.done') }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ step.description }}</p>

                                <div v-if="step.key === 'chat_widget' && step.meta?.embed_snippet" class="mt-3">
                                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('setup_index.embed_snippet') }}</p>
                                    <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">{{ step.meta.embed_snippet }}</pre>
                                    <button
                                        type="button"
                                        class="mt-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                                        @click="copy(step.meta.embed_snippet)"
                                    >
                                        {{ snippetCopied ? $t('setup_index.copied') : $t('setup_index.copy_snippet') }}
                                    </button>
                                </div>

                                <div v-if="step.key === 'email'" class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $t('setup_index.inbound_webhook') }}
                                    <code class="rounded bg-slate-100 dark:bg-slate-900 px-1.5 py-0.5">{{ guide.infrastructure?.inbound_webhook }}</code>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 dark:border-slate-800 px-5 py-3 sm:flex-row sm:items-center sm:justify-end">
                        <button
                            v-if="!step.complete"
                            type="button"
                            class="inline-flex h-9 w-full items-center justify-center rounded-lg px-4 text-sm font-medium text-slate-600 dark:text-slate-400 transition hover:bg-slate-50 dark:hover:bg-slate-800 dark:bg-slate-950 hover:text-slate-900 dark:hover:text-slate-100 dark:text-slate-100 sm:w-auto"
                            @click="completeStep(step.key)"
                        >{{ $t('setup_index.mark_done') }}</button>
                        <Link
                            :href="step.url"
                            class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 text-sm font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-800 dark:bg-slate-950 sm:w-auto"
                        >
                            {{ $t('setup_index.open') }}
                        </Link>
                    </div>
                </article>
            </div>

            <div v-if="!guidePaused" class="mt-8 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="p-5">
                    <template v-if="isDismissed">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $t('setup_index.setup_guide') }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $t('setup_index.return_to_your_dashboard_incomplete_items_will_keep_showing_warnings_u') }}</p>
                    </template>
                    <template v-else>
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $t('setup_index.ready_to_start_supporting') }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $t('setup_index.finish_setup_to_open_your_workspace_dashboard_missing_configuration_wi') }}</p>
                        <p v-if="dummyData.needs_choice" class="mt-2 text-sm text-amber-700 dark:text-amber-300">{{ $t('setup_index.choose_sample_or_empty') }}</p>
                        <p v-else-if="showDemoBootstrap && !guidePaused" class="mt-2 text-sm text-amber-700 dark:text-amber-300">{{ $t('setup_index.remove_default_demo_to_finish') }}</p>
                    </template>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 dark:border-slate-800 px-5 py-3 sm:flex-row sm:items-center sm:justify-end">
                    <Link
                        v-if="isDismissed"
                        href="/dashboard"
                        class="inline-flex h-9 w-full items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto"
                    >
                        {{ $t('setup_index.go_to_dashboard') }}
                    </Link>
                    <button
                        v-else
                        type="button"
                        class="inline-flex h-9 w-full items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                        :disabled="!canFinish"
                        @click="finish"
                    >{{ $t('setup_index.go_to_dashboard') }}</button>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>

<style scoped>
.welcome-banner-enter-active {
    animation: welcome-in 0.65s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.welcome-banner-leave-active {
    transition: opacity 0.4s ease, transform 0.4s ease;
}

.welcome-banner-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

.welcome-check {
    animation: welcome-check 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
}

.welcome-particle {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.7);
    left: calc(8% + (var(--i) * 7%));
    top: 50%;
    animation: welcome-confetti 1.2s ease-out calc(var(--i) * 0.05s) both;
}

.setup-step {
    animation: step-rise 0.45s ease both;
}

@keyframes welcome-in {
    from {
        opacity: 0;
        transform: translateY(-16px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes welcome-check {
    from {
        opacity: 0;
        transform: scale(0.5);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes welcome-confetti {
    from {
        opacity: 0;
        transform: translateY(0) scale(0);
    }

    30% {
        opacity: 1;
    }

    to {
        opacity: 0;
        transform: translateY(-48px) scale(1);
    }
}

@keyframes step-rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
