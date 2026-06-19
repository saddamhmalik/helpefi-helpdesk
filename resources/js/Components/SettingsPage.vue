<script setup>
import SettingsPageActions from './SettingsPageActions.vue';
import SettingsSectionInfo from './SettingsSectionInfo.vue';
import { useSettingsLayoutMeta } from '../composables/useSettingsLayoutMeta.js';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    headTitle: { type: String, default: '' },
    infoSection: { type: String, default: '' },
});

useSettingsLayoutMeta(() => ({
    title: props.title,
    description: props.description,
    headTitle: props.headTitle,
}));
</script>

<template>
    <SettingsPageActions v-if="$slots.actions">
        <slot name="actions" />
    </SettingsPageActions>
    <SettingsSectionInfo v-if="infoSection" :section="infoSection" />
    <slot />
</template>
