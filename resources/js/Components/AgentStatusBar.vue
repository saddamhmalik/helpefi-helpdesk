<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAgentStatusClock } from '../composables/useAgentStatusClock.js';
import { useRealtimeConnection } from '../composables/useRealtimeConnection.js';

const { t } = useI18n();
const page = usePage();
const { formatted: clockLabel, timezoneLabel, isoTimestamp } = useAgentStatusClock();
const { connected: realtimeConnected, configured: realtimeConfigured } = useRealtimeConnection();

const appVersion = computed(() => page.props.appVersion);
const workspaceName = computed(() => page.props.helpdesk?.name ?? null);
const currentYear = new Date().getFullYear();

const realtimeStatusLabel = computed(() => (
    realtimeConnected.value
        ? t('layouts.app.live')
        : t('layouts.app.reconnecting')
));
</script>

<template>
    <footer class="shrink-0 border-t agent-border-subtle bg-white/90 backdrop-blur-sm dark:bg-slate-900/90">
        <div class="flex h-7 items-center gap-3 px-4 sm:gap-4 sm:px-6">
            <p class="min-w-0 flex-1 truncate text-[11px] agent-text-subtle">
                <span v-if="workspaceName" class="font-medium agent-text">{{ workspaceName }}</span>
                <span v-if="workspaceName" class="mx-1.5 text-slate-300 dark:text-slate-600" aria-hidden="true">·</span>
                <span class="font-semibold tracking-tight agent-text">{{ t('layouts.app.helpdesk') }}</span>
                <span class="mx-1.5 text-slate-300 dark:text-slate-600" aria-hidden="true">·</span>
                <span>{{ t('layouts.app.footer_copyright', { year: currentYear }) }}</span>
            </p>

            <div class="flex shrink-0 items-center gap-2 sm:gap-2.5">
                <span
                    v-if="realtimeConfigured"
                    class="inline-flex items-center"
                    role="status"
                    :aria-label="realtimeStatusLabel"
                    :title="realtimeStatusLabel"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full transition-colors"
                        :class="realtimeConnected
                            ? 'bg-emerald-500 shadow-[0_0_0_2px_rgba(16,185,129,0.2)]'
                            : 'animate-pulse bg-amber-400'"
                    />
                </span>

                <time
                    class="hidden whitespace-nowrap text-[11px] tabular-nums agent-text-subtle sm:inline"
                    :datetime="isoTimestamp"
                    :title="timezoneLabel"
                >
                    {{ clockLabel }}
                    <span class="ml-1 text-slate-400 dark:text-slate-500">{{ timezoneLabel }}</span>
                </time>

                <span
                    v-if="appVersion"
                    class="rounded-md border border-slate-200/80 bg-slate-50 px-2 py-px font-mono text-[10px] font-medium tracking-wide text-slate-500 dark:border-slate-700/80 dark:bg-slate-800/80 dark:text-slate-400"
                >
                    v{{ appVersion }}
                </span>
            </div>
        </div>
    </footer>
</template>
