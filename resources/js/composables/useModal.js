import { nextTick, onUnmounted, watch } from 'vue';

const FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), textarea, input, select, [contenteditable="true"], [tabindex]:not([tabindex="-1"])';

function isFocusable(element) {
    if (element.getClientRects().length > 0) {
        return true;
    }

    const style = window.getComputedStyle(element);

    return style.position === 'fixed' && style.display !== 'none' && style.visibility !== 'hidden';
}

function focusableElements(root) {
    if (!root) {
        return [];
    }

    return [...root.querySelectorAll(FOCUSABLE_SELECTOR)].filter(isFocusable);
}

export function useBodyScrollLock(openRef) {
    watch(openRef, (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
    }, { immediate: true });

    onUnmounted(() => {
        document.body.style.overflow = '';
    });
}

export function useEscapeKey(openRef, onClose) {
    const handler = (event) => {
        if (event.key === 'Escape' && openRef.value) {
            onClose();
        }
    };

    watch(openRef, (open) => {
        if (open) {
            window.addEventListener('keydown', handler);
        } else {
            window.removeEventListener('keydown', handler);
        }
    }, { immediate: true });

    onUnmounted(() => {
        window.removeEventListener('keydown', handler);
    });
}

export function useAccessibleDialog(openRef, dialogRef, { onEscape, initialFocusRef } = {}) {
    let previousFocus = null;

    const handleKeydown = (event) => {
        if (!openRef.value || !dialogRef.value) {
            return;
        }

        if (event.key === 'Escape') {
            onEscape?.();

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const elements = focusableElements(dialogRef.value);

        if (!elements.length) {
            return;
        }

        const first = elements[0];
        const last = elements[elements.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    watch(openRef, async (open) => {
        if (open) {
            previousFocus = document.activeElement;
            await nextTick();
            const target = initialFocusRef?.value ?? focusableElements(dialogRef.value)[0];
            target?.focus();
            window.addEventListener('keydown', handleKeydown);

            return;
        }

        window.removeEventListener('keydown', handleKeydown);
        previousFocus?.focus?.();
        previousFocus = null;
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeydown);
    });
}
