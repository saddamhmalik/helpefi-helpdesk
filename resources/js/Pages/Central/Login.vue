<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import CentralSeoHead from '../../Components/CentralSeoHead.vue';

const props = defineProps({
    brand: { type: String, default: 'Helpdesk' },
    trialDays: { type: Number, default: 14 },
    centralDomain: { type: String, default: '' },
    prefillSlug: { type: String, default: '' },
    prefillEmail: { type: String, default: '' },
    seo: { type: Object, default: () => ({}) },
});

const form = useForm({
    slug: props.prefillSlug ?? '',
    email: props.prefillEmail ?? '',
});

const submit = () => {
    form.post('/login');
};

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
</script>

<template>
    <CentralSeoHead page="login" :brand="brand" :trial-days="trialDays" :seo="seo" />
    <CentralLayout :brand="brand" :trial-days="trialDays" :show-footer="false">
        <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-12 sm:px-6">
            <div class="mx-auto grid max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:grid-cols-5">
                <aside class="hidden bg-slate-950 p-8 text-white lg:col-span-2 lg:flex lg:flex-col lg:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">{{ $t('central.welcome_back') }}</h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-400">
                            {{ $t('central.enter_your_workspace_url_to_open_the_agent_login_for_your_team') }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-medium text-slate-300">{{ t('central.new_to_brand', { brand }) }}</p>
                        <p class="mt-1 text-sm text-white">{{ t('central.start_trial_no_card', { days: trialDays }) }}</p>
                        <Link href="/register" class="mt-3 inline-flex text-sm font-semibold text-blue-400 hover:text-blue-300">
                            {{ t('central.create_workspace_link') }}
                        </Link>
                    </div>
                </aside>

                <div class="p-8 lg:col-span-3">
                    <Link href="/" class="text-sm text-slate-500 hover:text-slate-700 lg:hidden">{{ t('central.back_to_home') }}</Link>
                    <h1 class="mt-4 text-2xl font-semibold text-slate-900 lg:mt-0">{{ $t('central.sign_in_to_your_workspace') }}</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ $t('central.well_send_you_to_your_teams_login_page') }}
                    </p>

                    <form class="mt-8 space-y-5" @submit.prevent="submit">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.workspace_url') }}</label>
                            <div class="flex overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                <input
                                    v-model="form.slug"
                                    type="text"
                                    required
                                    pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                                    class="min-w-0 flex-1 border-0 bg-transparent px-3.5 py-2.5 text-sm focus:outline-none focus:ring-0"
                                    :placeholder="$t('central.acme')"
                                    autofocus
                                />
                                <span v-if="centralDomain" class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">.{{ centralDomain }}</span>
                            </div>
                            <p v-if="form.errors.slug" class="mt-1.5 text-xs text-red-600">{{ form.errors.slug }}</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.email') }} <span class="font-normal text-slate-400">{{ $t('central.optional') }}</span></label>
                            <input v-model="form.email" type="email" :class="inputClass" :placeholder="$t('central.you_company_com')" />
                            <p class="mt-1.5 text-xs text-slate-500">{{ $t('central.prefills_the_sign-in_form_on_your_workspace') }}</p>
                        </div>

                        <button
                            type="submit"
                            class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-70"
                            :disabled="form.processing"
                        >{{ $t('central.continue_to_sign_in') }}</button>
                    </form>

                    <p class="mt-6 text-center text-sm text-slate-600 lg:hidden">
                        {{ t('central.no_workspace_yet') }}
                        <Link href="/register" class="font-medium text-blue-600 hover:text-blue-700">{{ t('central.start_your_free_trial') }}</Link>
                    </p>
                </div>
            </div>
        </div>
    </CentralLayout>
</template>
