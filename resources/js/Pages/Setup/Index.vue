<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import { useClipboard } from '../../composables/useClipboard.js';
import { useToast } from '../../composables/useToast.js';

const props = defineProps({
    guide: Object,
    welcome: Boolean,
});

const showWelcome = ref(props.welcome);
const { copied: snippetCopied, copy: copySnippet } = useClipboard();
const toast = useToast();
const progress = computed(() => props.guide?.progress ?? { completed: 0, total: 0 });
const canFinish = computed(() => progress.value.completed >= progress.value.total && progress.value.total > 0);
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
        toast.error('Could not copy to clipboard. Select the snippet and copy manually.');
    }
};
</script>

<template>
    <Head title="Workspace setup" />
    <AgentLayout>
        <div class="mx-auto max-w-3xl px-4 py-8">
            <Transition name="welcome-banner">
                <div
                    v-if="showWelcome"
                    class="welcome-banner relative mb-8 overflow-hidden rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-600 via-blue-600 to-indigo-600 p-6 text-white shadow-lg"
                >
                    <div class="welcome-confetti pointer-events-none absolute inset-0 overflow-hidden">
                        <span v-for="n in 12" :key="n" class="welcome-particle" :style="{ '--i': n }" />
                    </div>
                    <div class="relative flex items-start gap-4">
                        <div class="welcome-check flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/20 text-2xl font-bold backdrop-blur">
                            ✓
                        </div>
                        <div>
                            <p class="text-sm font-medium text-blue-100">Workspace ready</p>
                            <h2 class="mt-1 text-xl font-semibold">Welcome to {{ guide.workspace?.name }}</h2>
                            <p class="mt-2 text-sm text-blue-100">
                                Your helpdesk is live at
                                <span class="font-medium text-white">{{ guide.workspace?.domain }}</span>.
                                Complete the steps below to start supporting customers.
                            </p>
                        </div>
                    </div>
                </div>
            </Transition>

            <div class="mb-8">
                <p class="text-sm font-medium text-blue-600">Getting started</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900">Set up {{ guide.workspace?.name }}</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Complete these steps to configure your workspace at
                    <span class="font-medium text-slate-800">{{ guide.workspace?.domain }}</span>.
                </p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full bg-blue-600 transition-all duration-700 ease-out"
                        :style="{ width: `${progress.total ? (progress.completed / progress.total) * 100 : 0}%` }"
                    />
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ progress.completed }} of {{ progress.total }} required steps complete</p>
            </div>

            <div class="space-y-4">
                <article
                    v-for="(step, index) in guide.steps"
                    :key="step.key"
                    class="setup-step rounded-xl border bg-white shadow-sm"
                    :class="step.complete ? 'border-emerald-200' : 'border-slate-200'"
                    :style="{ animationDelay: `${index * 60}ms` }"
                >
                    <div class="p-5">
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors"
                                :class="step.complete ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ step.complete ? '✓' : '•' }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-base font-semibold text-slate-900">{{ step.title }}</h2>
                                    <span v-if="!step.required" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Optional</span>
                                    <span
                                        v-if="step.complete"
                                        class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700"
                                    >
                                        Done
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">{{ step.description }}</p>

                                <div v-if="step.key === 'chat_widget' && step.meta?.embed_snippet" class="mt-3">
                                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500">Embed snippet</p>
                                    <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">{{ step.meta.embed_snippet }}</pre>
                                    <button
                                        type="button"
                                        class="mt-2 text-sm font-medium text-blue-600 hover:text-blue-700"
                                        @click="copy(step.meta.embed_snippet)"
                                    >
                                        {{ snippetCopied ? 'Copied!' : 'Copy snippet' }}
                                    </button>
                                </div>

                                <div v-if="step.key === 'email_inbox'" class="mt-3 text-xs text-slate-500">
                                    Inbound webhook:
                                    <code class="rounded bg-slate-100 px-1.5 py-0.5">{{ guide.infrastructure?.inbound_webhook }}</code>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-end">
                        <button
                            v-if="!step.complete"
                            type="button"
                            class="inline-flex h-9 w-full items-center justify-center rounded-lg px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 sm:w-auto"
                            @click="completeStep(step.key)"
                        >
                            Mark done
                        </button>
                        <Link
                            :href="step.url"
                            class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto"
                        >
                            Open
                        </Link>
                    </div>
                </article>
            </div>

            <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="p-5">
                    <template v-if="isDismissed">
                        <p class="text-sm font-medium text-slate-900">Setup guide</p>
                        <p class="mt-1 text-sm text-slate-500">Return to your dashboard. Incomplete items will keep showing warnings until configured.</p>
                    </template>
                    <template v-else>
                        <p class="text-sm font-medium text-slate-900">Ready to start supporting customers?</p>
                        <p class="mt-1 text-sm text-slate-500">Finish setup to open your workspace dashboard. Missing configuration will still show warnings.</p>
                    </template>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-end">
                    <Link
                        v-if="isDismissed"
                        href="/dashboard"
                        class="inline-flex h-9 w-full items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto"
                    >
                        Go to dashboard
                    </Link>
                    <button
                        v-else
                        type="button"
                        class="inline-flex h-9 w-full items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                        :disabled="!canFinish"
                        @click="finish"
                    >
                        Go to dashboard
                    </button>
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
