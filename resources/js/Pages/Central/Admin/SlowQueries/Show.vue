<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import { useDateTime } from '../../../../composables/useDateTime.js';

const props = defineProps({
    slowQuery: Object,
    thresholdMs: Number,
});

const { formatDateTime } = useDateTime();
const copied = ref(null);

const formatBindings = (bindings) => {
    if (!bindings?.length) {
        return '';
    }

    return JSON.stringify(bindings, null, 2);
};

const copyText = async (key, text) => {
    if (!text) {
        return;
    }

    await navigator.clipboard.writeText(text);
    copied.value = key;

    window.setTimeout(() => {
        if (copied.value === key) {
            copied.value = null;
        }
    }, 1600);
};
</script>

<template>
    <Head :title="$t('central.slow_query_detail', { id: slowQuery.id })" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <PageHeader
                :title="$t('central.slow_query_detail', { id: slowQuery.id })"
                :description="$t('central.slow_query_detail_description', { duration: slowQuery.time_ms, threshold: thresholdMs })"
            >
                <template #actions>
                    <Link
                        href="/admin/slow-queries"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        {{ $t('central.back_to_slow_queries') }}
                    </Link>
                </template>
            </PageHeader>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div class="space-y-6">
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.query') }}</h2>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                @click="copyText('sql', slowQuery.sql)"
                            >
                                {{ copied === 'sql' ? $t('components.copied') : $t('components.copy') }}
                            </button>
                        </div>
                        <pre class="mt-4 max-h-[32rem] overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-relaxed text-slate-100">{{ slowQuery.sql }}</pre>
                    </div>

                    <div
                        v-if="slowQuery.bindings?.length"
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.bindings') }}</h2>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                @click="copyText('bindings', formatBindings(slowQuery.bindings))"
                            >
                                {{ copied === 'bindings' ? $t('components.copied') : $t('components.copy') }}
                            </button>
                        </div>
                        <pre class="mt-4 max-h-80 overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-relaxed text-slate-100">{{ formatBindings(slowQuery.bindings) }}</pre>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.duration') }}</h2>
                        <p class="mt-2 text-2xl font-semibold text-amber-700 dark:text-amber-300">{{ slowQuery.time_ms }} ms</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('central.when') }}: {{ formatDateTime(slowQuery.created_at) }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.connection') }}</h2>
                        <p class="mt-2 font-mono text-sm text-slate-800 dark:text-slate-200">{{ slowQuery.connection }}</p>
                        <p v-if="slowQuery.database_host" class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $t('central.database_host') }}: <span class="font-mono text-slate-700 dark:text-slate-300">{{ slowQuery.database_host }}</span></p>
                        <p v-if="slowQuery.database_name" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('central.database_name') }}: <span class="font-mono text-slate-700 dark:text-slate-300">{{ slowQuery.database_name }}</span></p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.route') }}</h2>
                        <p class="mt-2 font-mono text-sm text-slate-800 dark:text-slate-200">{{ slowQuery.route_name || '—' }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.source') }}</h2>
                        <p v-if="slowQuery.source_callable" class="mt-2 break-all text-sm font-medium text-slate-800 dark:text-slate-200">{{ slowQuery.source_callable }}</p>
                        <p v-if="slowQuery.source_file" class="mt-2 break-all font-mono text-xs text-slate-600 dark:text-slate-400">{{ slowQuery.source_file }}<span v-if="slowQuery.source_line">:{{ slowQuery.source_line }}</span></p>
                        <p v-if="!slowQuery.source_callable && !slowQuery.source_file" class="mt-2 text-sm text-slate-500 dark:text-slate-400">—</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.workspace') }}</h2>
                        <template v-if="slowQuery.tenant || slowQuery.tenant_id">
                            <p v-if="slowQuery.tenant?.name" class="mt-2 text-base font-medium text-slate-900 dark:text-slate-100">{{ slowQuery.tenant.name }}</p>
                            <p v-if="slowQuery.tenant?.slug" class="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ slowQuery.tenant.slug }}</p>
                            <p v-if="slowQuery.tenant_id" class="mt-2 break-all font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ slowQuery.tenant_id }}</p>
                            <Link
                                v-if="slowQuery.tenant?.slug"
                                :href="`/admin/tenants?q=${encodeURIComponent(slowQuery.tenant.slug)}`"
                                class="mt-3 inline-flex text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:hover:text-blue-200"
                            >
                                {{ $t('central.open_workspace') }}
                            </Link>
                        </template>
                        <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">—</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.request') }}</h2>
                        <template v-if="slowQuery.method || slowQuery.url">
                            <p v-if="slowQuery.method" class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ slowQuery.method }}</p>
                            <p v-if="slowQuery.url" class="mt-2 break-all text-xs text-slate-600 dark:text-slate-400">{{ slowQuery.url }}</p>
                        </template>
                        <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">—</p>
                    </div>
                </aside>
            </div>
        </div>
    </AdminLayout>
</template>
