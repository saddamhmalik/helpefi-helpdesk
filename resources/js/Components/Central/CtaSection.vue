<script setup>
defineProps({
    title: { type: String, default: '' },
    body: { type: String, default: '' },
    primaryLabel: { type: String, default: 'Start free trial' },
    primaryHref: { type: String, default: '/register' },
    secondaryLabel: { type: String, default: '' },
    secondaryHref: { type: String, default: '' },
    maxWidth: { type: String, default: '' },
    /** 'dark' (default) for hero/final CTAs on slate-950 bg, or 'light' for subdued */
    variant: { type: String, default: 'dark' },
});
</script>

<template>
    <section
        class="py-16 sm:py-24"
        :class="variant === 'dark' ? 'bg-slate-950' : 'bg-slate-50 dark:bg-slate-950'"
    >
        <div
            class="mx-auto px-4 text-center sm:px-6 lg:px-8"
            :class="maxWidth || (variant === 'dark' ? 'max-w-4xl' : 'max-w-3xl')"
        >
            <h2
                v-if="title"
                class="text-3xl font-bold tracking-tight sm:text-4xl"
                :class="variant === 'dark' ? 'text-white' : 'text-slate-900 dark:text-slate-100'"
            >
                {{ title }}
            </h2>
            <p
                v-if="body"
                class="mx-auto mt-4 max-w-2xl text-lg"
                :class="variant === 'dark' ? 'text-slate-400' : 'text-slate-600 dark:text-slate-400'"
            >
                {{ body }}
            </p>
            <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a
                    :href="primaryHref"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl px-10 py-4 text-sm font-bold shadow-xl transition sm:w-auto"
                    :class="variant === 'dark'
                        ? 'bg-white text-slate-900 hover:bg-slate-100'
                        : 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-500 hover:to-indigo-500'
                    "
                >
                    {{ primaryLabel }}
                </a>
                <a
                    v-if="secondaryLabel && secondaryHref"
                    :href="secondaryHref"
                    class="inline-flex w-full items-center justify-center rounded-2xl border px-10 py-4 text-sm font-semibold transition sm:w-auto"
                    :class="variant === 'dark'
                        ? 'border-white/20 text-white hover:bg-white/10'
                        : 'border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200'
                    "
                >
                    {{ secondaryLabel }}
                </a>
            </div>
        </div>
    </section>
</template>
