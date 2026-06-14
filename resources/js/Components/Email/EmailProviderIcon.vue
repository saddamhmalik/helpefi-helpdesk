<script setup>
defineProps({
    provider: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <span
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-1.5 dark:border-slate-700 dark:bg-slate-900"
        aria-hidden="true"
    >
        <svg v-if="provider === 'google'" class="h-full w-full" viewBox="0 0 24 24" role="img">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
        </svg>

        <svg v-else-if="provider === 'microsoft'" class="h-full w-full" viewBox="0 0 23 23" role="img">
            <path fill="#F25022" d="M1 1h10v10H1z" />
            <path fill="#7FBA00" d="M12 1h10v10H12z" />
            <path fill="#00A4EF" d="M1 12h10v10H1z" />
            <path fill="#FFB900" d="M12 12h10v10H12z" />
        </svg>

        <svg v-else-if="provider === 'zoho'" class="h-full w-full" viewBox="0 0 24 24" role="img">
            <path fill="#006FD6" d="M12.475 10.838V9.059c0-2.723 1.823-4.454 4.759-4.454 1.314 0 2.318.143 3.101.362v2.592c-.453-.094-1.218-.149-1.899-.149-1.939 0-2.848.957-2.848 2.875v.503h4.839v2.695h-4.839V17.03h7.223c.071-2.079 1.278-3.046 3.059-3.514 1.928-.509 2.517-.899 2.517-2.15 0-1.543-1.405-2.528-3.883-2.528-1.403 0-2.522.278-3.288.741V10.838H12.475z" />
        </svg>

        <svg v-else-if="provider === 'imap'" class="h-full w-full text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" role="img">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4m16 0l-2.293 2.293a1 1 0 01-1.414 0L12 14l-4.293 4.293a1 1 0 01-1.414 0L4 13" />
        </svg>

        <svg v-else class="h-full w-full text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" role="img">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 12h8m0 0l-3-3m3 3l-3 3" />
        </svg>
    </span>
</template>
