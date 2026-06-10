<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import CsatRatingForm from '../../Components/CsatRatingForm.vue';
import TicketMessageContent from '../../Components/TicketMessageContent.vue';
import { usePortalRoutes } from '../../composables/usePortalRoutes.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    number: String,
    email: String,
    ticket: Object,
    csat: Object,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();
const { portalPath } = usePortalRoutes();

const form = useForm({
    number: props.number || '',
    email: props.email || '',
});

const lookup = () => form.post(portalPath('/track'));
</script>

<template>
    <Head :title="$t('portal.track_request')" />
    <PortalLayout>
        <div class="mx-auto max-w-xl">
            <Link :href="portalPath()" class="text-sm text-blue-600 hover:text-blue-700">← Help Center</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $t('portal.track_your_request') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $t('portal.enter_your_ticket_number_and_email_to_view_status') }}</p>

            <form class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="lookup">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('portal.ticket_number') }}</label>
                    <input v-model="form.number" type="text" required placeholder="HD-00001" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('portal.email') }}</label>
                    <input v-model="form.email" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('portal.track') }}</button>
            </form>

            <div v-if="number && email && !ticket" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                No ticket found for that number and email.
            </div>

            <div v-if="ticket" class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ ticket.number }}</p>
                        <h2 class="text-xl font-semibold text-slate-900">{{ ticket.subject }}</h2>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700">{{ ticket.status?.name }}</span>
                </div>
                <div v-if="ticket.description" class="mt-4">
                    <TicketMessageContent :body="ticket.description" />
                </div>

                <div v-if="ticket.messages?.length" class="mt-6 border-t border-slate-100 pt-4">
                    <h3 class="text-sm font-semibold text-slate-900">{{ $t('portal.updates') }}</h3>
                    <div class="mt-3 space-y-3">
                        <div v-for="message in ticket.messages" :key="message.id" class="rounded-lg bg-slate-50 p-3 text-sm">
                            <div class="flex justify-between text-slate-500">
                                <span>{{ message.user?.name || 'Support' }}</span>
                                <span>{{ formatDateTime(message.created_at) }}</span>
                            </div>
                            <p class="mt-1 whitespace-pre-wrap text-slate-800">{{ message.body }}</p>
                        </div>
                    </div>
                </div>

                <CsatRatingForm
                    v-if="csat"
                    :csat="csat"
                    :ticket="ticket"
                    :guest="{ number, email }"
                    :action="portalPath('/csat')"
                />
            </div>

            <p class="mt-6 text-center text-sm text-slate-600">
                <Link :href="portalPath('/login')" class="text-blue-600 hover:text-blue-700">{{ $t('portal.sign_in') }}</Link>
                to see all your tickets, or
                <Link :href="portalPath('/register')" class="text-blue-600 hover:text-blue-700">{{ $t('portal.create_an_account') }}</Link>.
            </p>
        </div>
    </PortalLayout>
</template>
