<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, useId } from 'vue';
import AppCollapse from '../AppCollapse.vue';
import { buildFaqSchema, normalizeFaqItems } from '../../composables/useFaqSchema.js';
import { useFaqAccordion } from '../../composables/useFaqAccordion.js';

const props = defineProps({
    items: { type: Array, default: () => [] },
    eyebrow: { type: String, default: '' },
    title: { type: String, default: '' },
    headingTag: { type: String, default: 'h2' },
    headingClass: { type: String, default: 'mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100' },
    headerClass: { type: String, default: 'text-center' },
    id: { type: String, default: '' },
    sectionClass: { type: String, default: '' },
    listClass: { type: String, default: 'mt-10 space-y-3' },
    allowMultiple: { type: Boolean, default: false },
    defaultOpen: { type: [Number, Array], default: null },
    injectSchema: { type: Boolean, default: false },
    variant: { type: String, default: 'card' },
});

const uid = useId();
const sectionId = computed(() => props.id || `faq-${uid}`);
const headingId = computed(() => `${sectionId.value}-heading`);

const normalizedItems = computed(() => normalizeFaqItems(props.items));

const count = computed(() => normalizedItems.value.length);
const allowMultiple = computed(() => props.allowMultiple);
const defaultOpen = computed(() => props.defaultOpen);

const { isOpen, toggle, setHeaderRef, onHeaderKeydown } = useFaqAccordion({
    count,
    allowMultiple,
    defaultOpen,
});

const schemaJson = computed(() => {
    if (!props.injectSchema) {
        return '';
    }

    const schema = buildFaqSchema(normalizedItems.value);
    return schema ? JSON.stringify(schema) : '';
});

const questionId = (index) => `${sectionId.value}-question-${index}`;
const answerId = (index) => `${sectionId.value}-answer-${index}`;

const itemShellClass = computed(() => {
    if (props.variant === 'plain') {
        return '';
    }

    return 'overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 transition hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950 dark:hover:border-slate-600';
});

const triggerClass = computed(() => {
    if (props.variant === 'plain') {
        return 'flex w-full items-center justify-between gap-4 py-4 text-left text-sm font-semibold text-slate-900 dark:text-slate-100';
    }

    return 'flex w-full items-center justify-between gap-3 px-4 py-4 text-left sm:gap-4 sm:px-5 sm:py-4';
});

const answerShellClass = computed(() => {
    if (props.variant === 'plain') {
        return 'pb-4';
    }

    return 'border-t border-slate-200 px-4 pb-4 pt-2 dark:border-slate-800 sm:px-5 sm:pb-5';
});

const listWrapperClass = computed(() => (
    props.variant === 'plain' ? `${props.listClass} divide-y divide-slate-200 dark:divide-slate-800` : props.listClass
));
</script>

<template>
    <section
        v-if="normalizedItems.length"
        :id="sectionId"
        :class="sectionClass"
        :aria-labelledby="title ? headingId : undefined"
    >
        <Head v-if="schemaJson">
            <component :is="'script'" type="application/ld+json" head-key="faq-schema" v-html="schemaJson" />
        </Head>

        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <header v-if="eyebrow || title || $slots.eyebrow || $slots.title" :class="headerClass">
                <slot name="eyebrow">
                    <p v-if="eyebrow" class="text-sm font-semibold uppercase tracking-wider text-blue-600">
                        {{ eyebrow }}
                    </p>
                </slot>

                <slot name="title">
                    <component
                        :is="headingTag"
                        v-if="title"
                        :id="headingId"
                        :class="headingClass"
                    >
                        {{ title }}
                    </component>
                </slot>
            </header>

            <div :class="listWrapperClass">
                <article
                    v-for="(item, index) in normalizedItems"
                    :key="`${index}-${item.question}`"
                    :class="itemShellClass"
                >
                    <h3 class="m-0 text-sm font-semibold text-slate-900 dark:text-slate-100 sm:text-base">
                        <button
                            :id="questionId(index)"
                            :ref="(element) => setHeaderRef(index, element)"
                            type="button"
                            :class="triggerClass"
                            :aria-expanded="isOpen(index)"
                            :aria-controls="answerId(index)"
                            @click="toggle(index)"
                            @keydown="onHeaderKeydown(index, $event)"
                        >
                            <span>{{ item.question }}</span>
                            <svg
                                class="h-5 w-5 shrink-0 text-slate-400 transition-transform duration-300 dark:text-slate-500"
                                :class="isOpen(index) ? 'rotate-180' : ''"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </h3>

                    <AppCollapse :open="isOpen(index)">
                        <div
                            :id="answerId(index)"
                            role="region"
                            :aria-labelledby="questionId(index)"
                            :class="answerShellClass"
                        >
                            <slot name="answer" :item="item" :index="index">
                                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                    {{ item.answer }}
                                </p>
                            </slot>
                        </div>
                    </AppCollapse>
                </article>
            </div>
        </div>
    </section>
</template>
