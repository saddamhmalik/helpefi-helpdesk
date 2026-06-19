<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    settings: Object,
});

const { t } = useI18n();

const providerLabel = computed(() => {
    if (props.settings.mode === 'groq') {
        return 'Groq';
    }

    if (props.settings.mode === 'openai') {
        return 'OpenAI';
    }

    return 'Local fallback';
});

const form = useForm({
    enabled: props.settings.enabled,
    model: props.settings.model ?? '',
    deflection_enabled: props.settings.deflection_enabled ?? false,
    deflection_portal_enabled: props.settings.deflection_portal_enabled ?? true,
    deflection_widget_enabled: props.settings.deflection_widget_enabled ?? true,
    triage_enabled: props.settings.triage_enabled ?? false,
});

const save = () => {
    form.put('/settings/ai', { preserveScroll: true });
};
</script>

<template>
    <SettingsPage
        :title="$t('settings.ai_assistant')"
        :description="$t('settings.descriptions.ai_assistant')"
        info-section="ai_assistant"
    >
        <PlanFeatureBanner feature="ai" />

        <div class="space-y-6">
            <div class="max-w-2xl agent-card">
                <h2 class="text-base font-semibold agent-text">{{ $t('settings_ai.agent_assist') }}</h2>
                <div class="mb-6 mt-4 rounded-lg border agent-border agent-panel-muted px-4 py-3 text-sm text-slate-700 dark:text-slate-300">
                    <p>
                        Mode:
                        <span class="font-medium">{{ providerLabel }}</span>
                    </p>
                    <p v-if="settings.mode === 'local'" class="mt-1 text-xs agent-text-subtle">
                        {{ $t('settings_ai.openai_is_not_enabled_for_this_workspace_ai_features_use_a_built-in_fa') }}
                    </p>
                </div>

                <form class="space-y-4" @submit.prevent="save">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="form.enabled" type="checkbox" class="rounded agent-border" />
                        Enable AI features for agents
                    </label>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_ai.model_override') }}</label>
                        <input
                            v-model="form.model"
                            type="text"
                            class="w-full rounded-lg border agent-border px-3 py-2 text-sm"
                            placeholder="gpt-4o-mini"
                        />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="form.triage_enabled" type="checkbox" class="rounded agent-border" :disabled="!form.enabled" />
                        Auto-triage new tickets (set priority from subject)
                    </label>

                    <div class="border-t agent-border-subtle pt-5">
                        <h3 class="text-sm font-semibold agent-text">{{ $t('settings_ai.customer_deflection') }}</h3>
                        <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_ai.answer_questions_from_the_knowledge_base_before_creating_tickets') }}</p>

                        <div class="mt-4 space-y-3">
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input v-model="form.deflection_enabled" type="checkbox" class="rounded agent-border" />
                                Enable AI deflection bot
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input v-model="form.deflection_portal_enabled" type="checkbox" class="rounded agent-border" :disabled="!form.deflection_enabled" />
                                Show on customer portal
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input v-model="form.deflection_widget_enabled" type="checkbox" class="rounded agent-border" :disabled="!form.deflection_enabled" />
                                Show on live chat widget
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('settings_ai.save_settings') }}</button>
                </form>
            </div>
        </div>
    </SettingsPage>
</template>
