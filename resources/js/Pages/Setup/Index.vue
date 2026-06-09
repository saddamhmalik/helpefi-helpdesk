<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';

const props = defineProps({
    guide: Object,
});

const progress = computed(() => props.guide?.progress ?? { completed: 0, total: 0 });
const canFinish = computed(() => progress.value.completed >= progress.value.total && progress.value.total > 0);

const completeStep = (key) => {
    router.post(`/setup/steps/${key}`, {}, { preserveScroll: true });
};

const finish = () => {
    router.post('/setup/finish');
};

const copy = async (text) => {
    if (!text) {
        return;
    }

    await navigator.clipboard.writeText(text);
};
</script>

<template>
    <Head title="Workspace setup" />
    <AgentLayout>
        <div class="mx-auto max-w-3xl px-4 py-8">
            <div class="mb-8">
                <p class="text-sm font-medium text-blue-600">Getting started</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900">Set up {{ guide.workspace?.name }}</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Complete these steps to configure your workspace at
                    <span class="font-medium text-slate-800">{{ guide.workspace?.domain }}</span>.
                </p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full bg-blue-600 transition-all"
                        :style="{ width: `${progress.total ? (progress.completed / progress.total) * 100 : 0}%` }"
                    />
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ progress.completed }} of {{ progress.total }} required steps complete</p>
            </div>

            <div class="space-y-4">
                <article
                    v-for="step in guide.steps"
                    :key="step.key"
                    class="rounded-xl border bg-white p-5 shadow-sm"
                    :class="step.complete ? 'border-emerald-200' : 'border-slate-200'"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                                    :class="step.complete ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ step.complete ? '✓' : '•' }}
                                </span>
                                <h2 class="text-base font-semibold text-slate-900">{{ step.title }}</h2>
                                <span v-if="!step.required" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Optional</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">{{ step.description }}</p>

                            <div v-if="step.key === 'chat_widget' && step.meta?.embed_snippet" class="mt-3">
                                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500">Embed snippet</p>
                                <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">{{ step.meta.embed_snippet }}</pre>
                                <button type="button" class="mt-2 text-sm font-medium text-blue-600 hover:text-blue-700" @click="copy(step.meta.embed_snippet)">
                                    Copy snippet
                                </button>
                            </div>

                            <div v-if="step.key === 'realtime'" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                                <p class="font-medium text-slate-900">Local development</p>
                                <ol class="mt-2 list-decimal space-y-1 pl-5 text-slate-600">
                                    <li v-for="command in guide.infrastructure?.realtime?.commands" :key="command">
                                        <code class="rounded bg-white px-1.5 py-0.5 text-xs">{{ command }}</code>
                                    </li>
                                </ol>
                                <p class="mt-3 font-medium text-slate-900">Production queue worker</p>
                                <code class="mt-1 block rounded bg-white px-2 py-1 text-xs">{{ guide.infrastructure?.queue?.worker }}</code>
                            </div>

                            <div v-if="step.key === 'email_inbox'" class="mt-3 text-xs text-slate-500">
                                Inbound webhook:
                                <code class="rounded bg-slate-100 px-1.5 py-0.5">{{ guide.infrastructure?.inbound_webhook }}</code>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2">
                            <Link
                                :href="step.url"
                                class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Open
                            </Link>
                            <button
                                v-if="!step.complete"
                                type="button"
                                class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800"
                                @click="completeStep(step.key)"
                            >
                                Mark done
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <div class="mt-8 flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5">
                <div>
                    <p class="text-sm font-medium text-slate-900">Ready to start supporting customers?</p>
                    <p class="text-sm text-slate-500">Finish setup to open your workspace dashboard.</p>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canFinish"
                    @click="finish"
                >
                    Go to dashboard
                </button>
            </div>
        </div>
    </AgentLayout>
</template>
