<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Helpdesk" />
    <div class="min-h-screen bg-slate-950 text-white">
        <div class="mx-auto flex max-w-5xl flex-col px-6 py-16">
            <header class="flex items-center justify-between">
                <span class="text-lg font-semibold">Helpdesk</span>
                <div class="flex items-center gap-3">
                    <Link href="/login" class="text-sm font-medium text-slate-300 hover:text-white">
                        Sign in
                    </Link>
                    <Link href="/register" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900">
                        Start free trial
                    </Link>
                </div>
            </header>

            <main class="mt-24 max-w-2xl">
                <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">
                    Customer support software for growing teams
                </h1>
                <p class="mt-4 text-lg text-slate-300">
                    Launch a dedicated helpdesk workspace with tickets, live chat, knowledge base, and SLA tracking.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link href="/register" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">
                        Create your workspace
                    </Link>
                </div>
            </main>
        </div>
    </div>
</template>
