<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        default: 'helpefi',
    },
});
</script>

<template>
    <div class="min-h-screen agent-page-bg">
        <header class="border-b agent-border agent-panel">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <Link href="/dashboard" class="text-lg font-semibold agent-text">{{ $t('layouts.app.helpdesk') }}</Link>
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-6 py-8">
            <slot />
        </main>
    </div>
</template>
