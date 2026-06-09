<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppModal from './AppModal.vue';

const props = defineProps({
    ticketId: { type: Number, required: true },
    defaultEmail: { type: String, default: '' },
});

const menuOpen = ref(false);
const emailModalOpen = ref(false);

const emailForm = useForm({
    email: props.defaultEmail,
    include_conversation: true,
});

const openEmailModal = () => {
    menuOpen.value = false;
    emailForm.email = props.defaultEmail;
    emailForm.include_conversation = true;
    emailModalOpen.value = true;
};

const sendEmail = () => {
    emailForm.post(`/tickets/${props.ticketId}/export/email`, {
        preserveScroll: true,
        onSuccess: () => {
            emailModalOpen.value = false;
        },
    });
};
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            @click="menuOpen = !menuOpen"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export
            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div
            v-if="menuOpen"
            class="absolute right-0 z-20 mt-1 w-52 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
        >
            <a
                :href="`/tickets/${ticketId}/export/pdf`"
                class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="menuOpen = false"
            >
                Download PDF
            </a>
            <a
                :href="`/tickets/${ticketId}/export/pdf?conversation=0`"
                class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="menuOpen = false"
            >
                Download PDF (no conversation)
            </a>
            <button
                type="button"
                class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="openEmailModal"
            >
                Email as PDF…
            </button>
        </div>

        <div v-if="menuOpen" class="fixed inset-0 z-10" @click="menuOpen = false" />
    </div>

    <AppModal
        :open="emailModalOpen"
        title="Email ticket export"
        description="Send a PDF export of this ticket to an email address."
        size="md"
        @close="emailModalOpen = false"
    >
        <form class="space-y-4" @submit.prevent="sendEmail">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Recipient email</label>
                <input
                    v-model="emailForm.email"
                    type="email"
                    required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                />
                <p v-if="emailForm.errors.email" class="mt-1 text-sm text-red-600">{{ emailForm.errors.email }}</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="emailForm.include_conversation" type="checkbox" class="rounded border-slate-300" />
                Include conversation in PDF
            </label>
        </form>

        <template #footer>
            <div class="flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white"
                    @click="emailModalOpen = false"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    :disabled="emailForm.processing"
                    @click="sendEmail"
                >
                    {{ emailForm.processing ? 'Sending…' : 'Send email' }}
                </button>
            </div>
        </template>
    </AppModal>
</template>
