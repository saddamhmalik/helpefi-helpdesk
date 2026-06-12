<script setup>
import AppAvatar from './AppAvatar.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';

const { t } = useI18n();

const { user } = useAgentNavigation();
const open = ref(false);
const root = ref(null);

const initials = computed(() => user.value?.name ?? t('components.user_fallback'));

const logout = () => {
    open.value = false;
    router.post('/logout');
};

const toggle = () => {
    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

const onDocumentClick = (event) => {
    if (!root.value?.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex items-center gap-2 rounded-lg p-1.5 text-left transition agent-hover-surface"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            <AppAvatar :name="user?.name" :email="user?.email" size="sm" />
            <span class="hidden max-w-[8rem] truncate text-sm font-medium text-slate-700 dark:text-slate-300 dark:text-slate-200 lg:block">{{ initials }}</span>
            <svg class="hidden h-4 w-4 text-slate-400 dark:text-slate-500 lg:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <Transition name="dropdown">
            <div
                v-if="open"
                class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border agent-border agent-panel py-1 shadow-lg"
                role="menu"
            >
            <div class="border-b agent-border-subtle px-4 py-3">
                <p class="truncate text-sm font-semibold agent-text">{{ user?.name }}</p>
                <p class="truncate text-xs agent-text-subtle">{{ user?.email }}</p>
            </div>
            <Link
                href="/settings/profile"
                class="block px-4 py-2.5 text-sm agent-text-muted transition agent-hover-surface"
                role="menuitem"
                @click="close"
            >
                {{ $t('components.profile_and_password') }}
            </Link>
            <Link
                href="/notifications"
                class="block px-4 py-2.5 text-sm agent-text-muted transition agent-hover-surface"
                role="menuitem"
                @click="close"
            >
                {{ $t('components.notifications') }}
            </Link>
            <button
                type="button"
                class="block w-full px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50 dark:bg-red-950/40 dark:hover:bg-red-950/40"
                role="menuitem"
                @click="logout"
            >
                {{ $t('components.sign_out') }}
            </button>
            </div>
        </Transition>
    </div>
</template>
