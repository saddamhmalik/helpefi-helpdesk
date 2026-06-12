<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    csat: Object,
    ticket: Object,
    guest: Object,
    action: String,
});

const selectedRating = ref(props.csat?.submitted?.rating ?? 0);
const hoverRating = ref(0);

const form = useForm({
    rating: props.csat?.submitted?.rating ?? null,
    comment: '',
    number: props.guest?.number ?? '',
    email: props.guest?.email ?? '',
});

const displayRating = computed(() => hoverRating.value || selectedRating.value);

const showForm = computed(() => props.csat?.enabled && props.csat?.eligible && !props.csat?.submitted);

const submitted = computed(() => props.csat?.submitted);

const selectRating = (value) => {
    selectedRating.value = value;
    form.rating = value;
};

const submit = () => {
    form.rating = selectedRating.value;
    form.post(props.action, { preserveScroll: true });
};
</script>

<template>
    <div v-if="csat?.enabled && (showForm || submitted)" class="mt-6 border-t border-slate-100 dark:border-slate-800 pt-6">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('components.how_was_support_experience') }}</h3>

        <div v-if="submitted" class="mt-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-900">
            <p class="font-medium">{{ $t('components.thank_you_for_your_feedback') }}</p>
            <div class="mt-2 flex items-center gap-1">
                <span v-for="star in 5" :key="star" class="text-lg" :class="star <= submitted.rating ? 'text-amber-400' : 'text-slate-300'">★</span>
                <span class="ml-2 text-slate-600 dark:text-slate-400">{{ submitted.rating }}/5</span>
            </div>
            <p v-if="submitted.comment" class="mt-2 whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ submitted.comment }}</p>
        </div>

        <form v-else-if="showForm" class="mt-4 space-y-4" @submit.prevent="submit">
            <div class="flex gap-1">
                <button
                    v-for="star in 5"
                    :key="star"
                    type="button"
                    class="text-2xl transition-colors"
                    :class="star <= displayRating ? 'text-amber-400' : 'text-slate-300 hover:text-amber-200'"
                    @click="selectRating(star)"
                    @mouseenter="hoverRating = star"
                    @mouseleave="hoverRating = 0"
                >
                    ★
                </button>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ $t('components.comment') }}
                    <span v-if="!csat.comment_required" class="font-normal text-slate-500 dark:text-slate-400">{{ $t('components.optional') }}</span>
                </label>
                <textarea
                    v-model="form.comment"
                    rows="3"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm"
                    :required="csat.comment_required"
                    :placeholder="$t('components.tell_us_more_about_your_experience')"
                />
                <p v-if="form.errors.comment" class="mt-1 text-sm text-red-600">{{ form.errors.comment }}</p>
            </div>

            <p v-if="form.errors.rating" class="text-sm text-red-600">{{ form.errors.rating }}</p>

            <button
                type="submit"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                :disabled="form.processing || !selectedRating"
            >
                {{ $t('components.submit_feedback') }}
            </button>
        </form>
    </div>
</template>
