<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    helpCenter: {
        type: Object,
        required: true,
    },
    tone: {
        type: String,
        default: 'dark',
    },
});
</script>

<template>
    <div
        class="rounded-xl border p-4"
        :class="tone === 'dark' ? 'border-white/10 bg-white/5' : 'border-slate-200/80 bg-slate-50/80 dark:border-slate-800/80 dark:bg-slate-900/80'"
    >
        <p
            class="text-xs font-medium uppercase tracking-wide"
            :class="tone === 'dark' ? 'text-slate-400' : 'text-slate-500 dark:text-slate-400'"
        >
            {{ $t('auth.customer_portal') }}
        </p>
        <p
            class="mt-1.5 text-sm leading-relaxed"
            :class="tone === 'dark' ? 'text-slate-300' : 'text-slate-600 dark:text-slate-400'"
        >
            {{ $t('auth.customer_portal_description') }}
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                :href="helpCenter.homeUrl"
                class="rounded-lg px-3 py-2 text-sm font-medium transition"
                :class="tone === 'dark' ? 'bg-white/10 text-white hover:bg-white/15' : 'bg-white text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-700 dark:hover:bg-slate-800'"
            >
                {{ helpCenter.title }}
            </Link>
            <Link
                :href="helpCenter.loginUrl"
                class="rounded-lg px-3 py-2 text-sm font-medium transition"
                :class="tone === 'dark' ? 'text-blue-300 hover:text-blue-200' : 'text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:hover:text-blue-200'"
            >
                {{ $t('auth.customer_sign_in') }}
            </Link>
            <Link
                :href="helpCenter.registerUrl"
                class="rounded-lg px-3 py-2 text-sm font-medium transition"
                :class="tone === 'dark' ? 'text-blue-300 hover:text-blue-200' : 'text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:hover:text-blue-200'"
            >
                {{ $t('auth.customer_register') }}
            </Link>
            <Link
                :href="helpCenter.submitUrl"
                class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-blue-500"
            >
                {{ $t('auth.submit_request') }}
            </Link>
        </div>
    </div>
</template>
