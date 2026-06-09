import { ref } from 'vue';

function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    textarea.setSelectionRange(0, text.length);

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    document.body.removeChild(textarea);

    return copied;
}

export async function copyToClipboard(text) {
    const value = typeof text === 'string' ? text : '';

    if (! value) {
        return false;
    }

    if (navigator.clipboard?.writeText && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(value);

            return true;
        } catch {
        }
    }

    return fallbackCopy(value);
}

export function useClipboard(resetMs = 2000) {
    const copied = ref(false);
    let resetTimer = null;

    const copy = async (text) => {
        const success = await copyToClipboard(text);

        if (! success) {
            copied.value = false;

            return false;
        }

        copied.value = true;

        if (resetTimer) {
            clearTimeout(resetTimer);
        }

        resetTimer = window.setTimeout(() => {
            copied.value = false;
            resetTimer = null;
        }, resetMs);

        return true;
    };

    return {
        copied,
        copy,
    };
}
