<script setup>
defineProps({
    provider: {
        type: String,
        required: true,
    },
    consoleUrl: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/60 dark:bg-amber-950/40">
        <p class="text-sm font-medium text-amber-950 dark:text-amber-100">{{ $t(`settings_email.${provider}_oauth_setup_title`) }}</p>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-xs leading-relaxed text-amber-900 dark:text-amber-200">
            <li v-if="provider === 'google'">
                <a
                    v-if="consoleUrl"
                    :href="consoleUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium underline hover:no-underline"
                >{{ $t('settings_email.google_gmail_setup_enable_api') }}</a>
                <span v-else>{{ $t('settings_email.google_gmail_setup_enable_api') }}</span>
                {{ $t('settings_email.google_gmail_setup_same_project') }}
            </li>
            <li v-else-if="provider === 'microsoft'">
                <a
                    v-if="consoleUrl"
                    :href="consoleUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium underline hover:no-underline"
                >{{ $t('settings_email.microsoft_outlook_setup_register_app') }}</a>
                <span v-else>{{ $t('settings_email.microsoft_outlook_setup_register_app') }}</span>
                {{ $t('settings_email.microsoft_outlook_setup_register_app_suffix') }}
            </li>
            <li v-else-if="provider === 'zoho'">
                <a
                    v-if="consoleUrl"
                    :href="consoleUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium underline hover:no-underline"
                >{{ $t('settings_email.zoho_mail_setup_create_client') }}</a>
                <span v-else>{{ $t('settings_email.zoho_mail_setup_create_client') }}</span>
                {{ $t('settings_email.zoho_mail_setup_create_client_suffix') }}
            </li>
            <li>{{ $t(`settings_email.${provider}_oauth_setup_env_credentials`) }}</li>
            <li v-if="provider === 'google'">{{ $t('settings_email.google_gmail_setup_consent_screen') }}</li>
            <li v-else-if="provider === 'microsoft'">{{ $t('settings_email.microsoft_outlook_setup_api_permissions') }}</li>
            <li v-else-if="provider === 'zoho'">{{ $t('settings_email.zoho_mail_setup_scopes') }}</li>
            <li>{{ $t(`settings_email.${provider}_oauth_setup_redirect_uri`) }}</li>
            <li v-if="provider === 'google'">{{ $t('settings_email.google_gmail_setup_wait_propagation') }}</li>
            <li>{{ $t(`settings_email.${provider}_oauth_setup_connect_and_test`) }}</li>
        </ol>
    </div>
</template>
