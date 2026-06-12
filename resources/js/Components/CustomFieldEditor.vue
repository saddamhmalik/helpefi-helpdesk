<script setup>
const props = defineProps({
    fields: { type: Array, required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});

const emit = defineEmits(['add', 'remove']);

const addField = () => emit('add');
const removeField = (index) => emit('remove', index);
</script>

<template>
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
            </div>
            <button type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800" @click="addField">
                {{ $t('components.add_field') }}
            </button>
        </div>

        <div v-if="!fields.length" class="mt-4 rounded-lg border border-dashed border-slate-200 dark:border-slate-800 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
            {{ $t('components.no_custom_fields_yet') }}
        </div>

        <div v-else class="mt-4 space-y-4">
            <div
                v-for="(field, index) in fields"
                :key="index"
                class="rounded-lg border border-slate-200 dark:border-slate-800 p-4"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('components.field_key') }}</label>
                        <input v-model="field.name" type="text" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" :placeholder="$t('components.field_key_placeholder')" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('components.label') }}</label>
                        <input v-model="field.label" type="text" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" :placeholder="$t('components.account_id_2')" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('components.type') }}</label>
                        <select v-model="field.type" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm">
                            <option value="text">{{ $t('components.text') }}</option>
                            <option value="textarea">{{ $t('components.long_text') }}</option>
                            <option value="number">{{ $t('components.number') }}</option>
                            <option value="email">{{ $t('components.email') }}</option>
                            <option value="select">{{ $t('components.select') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <input v-model="field.required" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                            {{ $t('components.required') }}
                        </label>
                    </div>
                </div>

                <div v-if="field.type === 'select'" class="mt-3">
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('components.options_one_per_line') }}</label>
                    <textarea
                        :value="(field.options ?? []).join('\n')"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm"
                        @input="field.options = $event.target.value.split('\n').map((line) => line.trim()).filter(Boolean)"
                    />
                </div>

                <button type="button" class="mt-3 text-sm text-red-600 hover:text-red-700 dark:text-red-300" @click="removeField(index)">
                    {{ $t('components.remove_field') }}
                </button>
            </div>
        </div>
    </div>
</template>
