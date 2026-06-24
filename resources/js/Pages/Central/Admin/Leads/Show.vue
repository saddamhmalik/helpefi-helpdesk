<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import { adminInputClass, usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';
import { useDateTime } from '../../../../composables/useDateTime.js';

const props = defineProps({
    lead: Object,
    sources: Object,
    intents: Object,
    statuses: Object,
});

const { formatDateTime } = useDateTime();
const { can } = usePlatformAdmin();
const canManage = can('leads.manage');

const notesForm = useForm({
    notes: props.lead.notes ?? '',
});

const label = (map, key) => map?.[key] ?? key;

const updateStatus = (status) => {
    router.put(`/admin/leads/${props.lead.id}/status`, { status }, { preserveScroll: true });
};

const saveNotes = () => {
    notesForm.put(`/admin/leads/${props.lead.id}/notes`, { preserveScroll: true });
};

const chatTranscript = props.lead.metadata?.chat_transcript ?? [];
</script>

<template>
    <Head :title="`Lead · ${lead.email}`" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <PageHeader :title="lead.name || lead.email" :description="`${label(sources, lead.source)} · ${label(intents, lead.intent)}`">
                <template #actions>
                    <Link href="/admin/leads" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400">← Back to leads</Link>
                </template>
            </PageHeader>

            <div class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</p>
                            <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ lead.email }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Company</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ lead.company || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ label(statuses, lead.status) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Marketing consent</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                {{ lead.has_marketing_consent ? formatDateTime(lead.marketing_consent_at) : 'No opt-in recorded' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Created</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ formatDateTime(lead.created_at) }}</p>
                        </div>
                        <div v-if="lead.pending_registration_id">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending registration</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">#{{ lead.pending_registration_id }}</p>
                        </div>
                    </div>

                    <div v-if="lead.message" class="mt-6">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Message</p>
                        <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ lead.message }}</p>
                    </div>

                    <div v-if="canManage" class="mt-6 flex flex-wrap gap-2">
                        <button
                            v-for="(statusLabel, statusKey) in statuses"
                            :key="statusKey"
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                            :class="lead.status === statusKey
                                ? 'border-blue-600 bg-blue-600 text-white'
                                : 'border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'"
                            @click="updateStatus(statusKey)"
                        >
                            {{ statusLabel }}
                        </button>
                    </div>
                </section>

                <section v-if="chatTranscript.length" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Chatbot transcript</p>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="(entry, index) in chatTranscript"
                            :key="index"
                            class="rounded-xl px-3 py-2 text-sm"
                            :class="entry.role === 'user'
                                ? 'bg-violet-50 text-violet-950 dark:bg-violet-950/30 dark:text-violet-100'
                                : 'bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-200'"
                        >
                            <p class="text-[10px] font-semibold uppercase tracking-wide opacity-70">{{ entry.role }}</p>
                            <p class="mt-1 whitespace-pre-wrap">{{ entry.text }}</p>
                        </div>
                    </div>
                </section>

                <section v-if="canManage" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Internal notes</p>
                    <form class="mt-4 space-y-3" @submit.prevent="saveNotes">
                        <textarea v-model="notesForm.notes" rows="5" :class="adminInputClass" placeholder="Follow-up notes for sales or marketing..." />
                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
                            :disabled="notesForm.processing"
                        >
                            Save notes
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
