<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useDateTime } from '../composables/useDateTime.js';
import { formInputClass, formTextareaClass } from '../composables/useFormControls.js';

const { formatDateTime } = useDateTime();

const props = defineProps({
    ticketId: { type: Number, required: true },
    conversations: { type: Array, default: () => [] },
});

const expandedId = ref(null);
const showCreate = ref(false);
const replyBodies = ref({});

const createForm = useForm({
    recipient_email: '',
    recipient_name: '',
    subject: '',
    body: '',
});

const toggleExpanded = (conversationId) => {
    expandedId.value = expandedId.value === conversationId ? null : conversationId;
};

const submitCreate = () => {
    createForm.post(`/tickets/${props.ticketId}/side-conversations`, {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });
};

const submitReply = (conversationId) => {
    router.post(`/tickets/${props.ticketId}/side-conversations/${conversationId}/reply`, {
        body: replyBodies.value[conversationId] ?? '',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            replyBodies.value[conversationId] = '';
        },
    });
};

const closeConversation = (conversationId) => {
    router.patch(`/tickets/${props.ticketId}/side-conversations/${conversationId}/close`, {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <section class="px-4 py-3">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('components.side_conversations') }}</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $t('components.email_third_parties_without_exposing_the_main_thread') }}</p>
            </div>
            <button
                type="button"
                class="rounded-md border border-slate-200 dark:border-slate-800 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800"
                @click="showCreate = !showCreate"
            >
                {{ showCreate ? $t('components.cancel') : $t('components.new') }}
            </button>
        </div>

        <form v-if="showCreate" class="mt-3 space-y-2 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/70 p-3" @submit.prevent="submitCreate">
            <input v-model="createForm.recipient_email" type="email" required :placeholder="$t('components.recipient_email')" :class="formInputClass" />
            <input v-model="createForm.recipient_name" type="text" :placeholder="$t('components.recipient_name_optional')" :class="formInputClass" />
            <input v-model="createForm.subject" type="text" required :placeholder="$t('components.subject')" :class="formInputClass" />
            <textarea v-model="createForm.body" required rows="4" :placeholder="$t('components.message_to_external_party')" :class="formTextareaClass" />
            <button
                type="submit"
                class="w-full rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                :disabled="createForm.processing"
            >
                {{ $t('components.send_side_email') }}
            </button>
        </form>

        <ul v-if="conversations.length" class="mt-3 space-y-2">
            <li
                v-for="conversation in conversations"
                :key="conversation.id"
                class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800"
            >
                <button
                    type="button"
                    class="flex w-full items-start justify-between gap-2 px-3 py-2 text-left hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800"
                    @click="toggleExpanded(conversation.id)"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ conversation.subject }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ conversation.recipient_email }}</p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        :class="conversation.status === 'open' ? 'bg-emerald-100 text-emerald-800 dark:text-emerald-200' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400'"
                    >
                        {{ conversation.status }}
                    </span>
                </button>

                <div v-if="expandedId === conversation.id" class="border-t border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-3">
                    <div class="max-h-48 space-y-2 overflow-y-auto">
                        <div
                            v-for="message in conversation.messages"
                            :key="message.id"
                            class="rounded-lg px-2.5 py-2 text-xs"
                            :class="message.is_inbound ? 'bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-200' : 'bg-blue-50 dark:bg-blue-950/40 text-blue-950'"
                        >
                            <div class="mb-1 flex items-center justify-between gap-2 text-[10px] text-slate-500 dark:text-slate-400">
                                <span>{{ message.is_inbound ? conversation.recipient_email : message.user?.name || $t('components.agent') }}</span>
                                <span>{{ formatDateTime(message.created_at) }}</span>
                            </div>
                            <div class="prose prose-sm max-w-none" v-html="message.body" />
                        </div>
                    </div>

                    <form v-if="conversation.status === 'open'" class="mt-3 space-y-2" @submit.prevent="submitReply(conversation.id)">
                        <textarea
                            v-model="replyBodies[conversation.id]"
                            rows="3"
                            required
                            :placeholder="$t('components.reply_to_external_party')"
                            :class="formTextareaClass"
                        />
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700"
                            >
                                {{ $t('components.send_reply') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800"
                                @click="closeConversation(conversation.id)"
                            >
                                {{ $t('components.close') }}
                            </button>
                        </div>
                    </form>
                </div>
            </li>
        </ul>

        <p v-else-if="!showCreate" class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ $t('components.no_side_conversations_yet') }}</p>
    </section>
</template>
