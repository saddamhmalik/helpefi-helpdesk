<script setup>
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Workspace unavailable" />
    <div class="flex min-h-screen items-center justify-center bg-slate-100 px-4">
        <div class="max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wider text-red-600">Access suspended</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">This workspace has been blocked</h1>
            <p class="mt-3 text-sm text-slate-600">
                Contact platform support if you believe this is a mistake.
            </p>
        </div>
    </div>
</template>
