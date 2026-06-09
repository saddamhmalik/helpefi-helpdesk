<script setup>
import { useForm } from '@inertiajs/vue3';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    enabled: props.settings.enabled,
    model: props.settings.model ?? '',
    deflection_enabled: props.settings.deflection_enabled ?? false,
    deflection_portal_enabled: props.settings.deflection_portal_enabled ?? true,
    deflection_widget_enabled: props.settings.deflection_widget_enabled ?? true,
});

const save = () => {
    form.put('/settings/ai', { preserveScroll: true });
};
</script>

<template>
    <SettingsLayout
        title="AI assistance"
        description="Agent assist tools and customer-facing AI deflection on the portal and chat widget."
    >
        <div class="space-y-6">
            <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Agent assist</h2>
                <div class="mb-6 mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <p>
                        Mode:
                        <span class="font-medium">{{ settings.mode === 'openai' ? 'OpenAI' : 'Local fallback' }}</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Set <code class="rounded bg-white px-1">OPENAI_API_KEY</code> in <code class="rounded bg-white px-1">.env</code> to use OpenAI.
                    </p>
                </div>

                <form class="space-y-4" @submit.prevent="save">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300" />
                        Enable AI features for agents
                    </label>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Model override</label>
                        <input
                            v-model="form.model"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            placeholder="gpt-4o-mini"
                        />
                    </div>

                    <div class="border-t border-slate-100 pt-5">
                        <h3 class="text-sm font-semibold text-slate-900">Customer deflection</h3>
                        <p class="mt-1 text-sm text-slate-500">Answer questions from the knowledge base before creating tickets.</p>

                        <div class="mt-4 space-y-3">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="form.deflection_enabled" type="checkbox" class="rounded border-slate-300" />
                                Enable AI deflection bot
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="form.deflection_portal_enabled" type="checkbox" class="rounded border-slate-300" :disabled="!form.deflection_enabled" />
                                Show on customer portal
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="form.deflection_widget_enabled" type="checkbox" class="rounded border-slate-300" :disabled="!form.deflection_enabled" />
                                Show on live chat widget
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">
                        Save settings
                    </button>
                </form>
            </div>
        </div>
    </SettingsLayout>
</template>
