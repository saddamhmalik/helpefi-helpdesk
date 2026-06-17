<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    siteKey: { type: String, required: true },
});

const emit = defineEmits(['token', 'error', 'expired']);

const container = ref(null);
const widgetId = ref(null);

const loadScript = () => new Promise((resolve, reject) => {
    if (window.turnstile) {
        resolve();
        return;
    }

    const existing = document.querySelector('script[data-turnstile-script]');
    if (existing) {
        existing.addEventListener('load', () => resolve(), { once: true });
        existing.addEventListener('error', () => reject(new Error('Turnstile failed to load')), { once: true });
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    script.async = true;
    script.defer = true;
    script.dataset.turnstileScript = 'true';
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Turnstile failed to load'));
    document.head.appendChild(script);
});

const renderWidget = async () => {
    await loadScript();

    if (! container.value || ! window.turnstile || widgetId.value !== null) {
        return;
    }

    widgetId.value = window.turnstile.render(container.value, {
        sitekey: props.siteKey,
        theme: 'auto',
        callback: (token) => emit('token', token),
        'error-callback': () => emit('error'),
        'expired-callback': () => {
            emit('expired');
            emit('token', '');
        },
    });
};

const reset = () => {
    if (widgetId.value !== null && window.turnstile) {
        window.turnstile.reset(widgetId.value);
    }

    emit('token', '');
};

onMounted(renderWidget);

watch(() => props.siteKey, () => {
    reset();
    renderWidget();
});

onBeforeUnmount(() => {
    if (widgetId.value !== null && window.turnstile) {
        window.turnstile.remove(widgetId.value);
    }
});

defineExpose({ reset });
</script>

<template>
    <div ref="container" class="min-h-[65px]" />
</template>
