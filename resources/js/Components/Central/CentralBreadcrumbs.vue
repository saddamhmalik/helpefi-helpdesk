<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCentralBreadcrumbs } from '../../composables/useCentralBreadcrumbs.js';

const props = defineProps({
    items: { type: Array, default: null },
    variant: { type: String, default: 'dark' },
});

const { crumbs } = useCentralBreadcrumbs();

const resolved = computed(() => (Array.isArray(props.items) ? props.items : crumbs.value));

const isHashLink = (href) => typeof href === 'string' && href.startsWith('/#');

const containerClass = computed(() => (
    props.variant === 'light'
        ? 'text-slate-500 dark:text-slate-400'
        : 'text-slate-300/80'
));

const linkClass = computed(() => (
    props.variant === 'light'
        ? 'transition hover:text-slate-900 dark:hover:text-slate-100'
        : 'transition hover:text-white'
));

const currentClass = computed(() => (
    props.variant === 'light'
        ? 'text-slate-700 dark:text-slate-200'
        : 'text-slate-200'
));
</script>

<template>
    <nav aria-label="Breadcrumb" class="mb-8 text-sm" :class="containerClass">
        <ol class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-1">
            <li
                v-for="(item, index) in resolved"
                :key="`${index}-${item?.href ?? item?.label ?? 'crumb'}`"
                class="inline-flex min-w-0 max-w-full items-center gap-x-1.5"
            >
                <span v-if="index > 0" class="shrink-0 select-none" aria-hidden="true">/</span>

                <template v-if="item?.href && index < resolved.length - 1">
                    <a
                        v-if="isHashLink(item.href)"
                        :href="item.href"
                        :class="[linkClass, 'min-w-0 truncate max-w-[22rem] sm:max-w-none']"
                    >
                        {{ item.label }}
                    </a>
                    <Link
                        v-else
                        :href="item.href"
                        :class="[linkClass, 'min-w-0 truncate max-w-[22rem] sm:max-w-none']"
                    >
                        {{ item.label }}
                    </Link>
                </template>

                <span
                    v-else
                    :class="[currentClass, 'min-w-0 truncate max-w-[22rem] sm:max-w-none']"
                    :aria-current="index === resolved.length - 1 ? 'page' : null"
                >
                    {{ item?.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>

