<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    viewers: { type: Array, default: () => [] },
});

const viewing = computed(() => props.viewers.filter((viewer) => !viewer.composing));
const replying = computed(() => props.viewers.filter((viewer) => viewer.composing));

const names = (list) => list.map((viewer) => viewer.name).join(', ');

const collisionVerb = (count) => (count === 1 ? t('components.collision_is') : t('components.collision_are'));
</script>

<template>
    <div v-if="viewers.length" class="border-b border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-3 py-2 text-xs text-amber-900">
        <p v-if="replying.length">
            {{ $t('components.collision_replying', { names: names(replying), verb: collisionVerb(replying.length) }) }}
        </p>
        <p v-else-if="viewing.length">
            {{ $t('components.collision_viewing', { names: names(viewing), verb: collisionVerb(viewing.length) }) }}
        </p>
    </div>
</template>
