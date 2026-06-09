<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PortalDeflectionBot from '../Components/PortalDeflectionBot.vue';
import { usePortalRoutes } from '../composables/usePortalRoutes.js';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isCustomer = computed(() => user.value?.is_customer);
const { brand, portalPath } = usePortalRoutes();

const headerStyle = computed(() => {
    const styles = {};

    if (brand.value.primary_color) {
        styles.borderBottomColor = brand.value.primary_color;
    }

    return styles;
});

const titleStyle = computed(() => {
    if (!brand.value.primary_color) {
        return {};
    }

    return { color: brand.value.primary_color };
});

const logout = () => router.post(portalPath('/logout'));
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white" :style="headerStyle">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <Link :href="portalPath()" class="text-lg font-semibold text-slate-900" :style="titleStyle">
                    {{ brand.portal_title || 'Help Center' }}
                </Link>
                <nav class="flex items-center gap-4 text-sm">
                    <Link :href="portalPath('/search')" class="text-slate-600 hover:text-slate-900">Search</Link>
                    <Link :href="portalPath('/services')" class="text-slate-600 hover:text-slate-900">Services</Link>
                    <Link :href="portalPath('/submit')" class="text-slate-600 hover:text-slate-900">Submit request</Link>
                    <template v-if="isCustomer">
                        <Link :href="portalPath('/my-tickets')" class="text-slate-600 hover:text-slate-900">My tickets</Link>
                        <button type="button" class="text-slate-600 hover:text-slate-900" @click="logout">Log out</button>
                    </template>
                    <template v-else>
                        <Link :href="portalPath('/track')" class="text-slate-600 hover:text-slate-900">Track request</Link>
                        <Link :href="portalPath('/login')" class="text-slate-600 hover:text-slate-900">Sign in</Link>
                        <Link :href="portalPath('/register')" class="rounded-lg bg-blue-600 px-3 py-1.5 text-white hover:bg-blue-700">Register</Link>
                    </template>
                    <Link href="/login" class="text-blue-600 hover:text-blue-700">Agent login</Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-8">
            <slot />
        </main>

        <PortalDeflectionBot />
    </div>
</template>
