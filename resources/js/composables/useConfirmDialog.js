import { ref } from 'vue';

export function useConfirmDialog() {
    const state = ref({
        open: false,
        title: 'Confirm',
        message: '',
        confirmLabel: 'Confirm',
        variant: 'danger',
        action: null,
    });

    const ask = ({ title = 'Confirm', message, confirmLabel = 'Confirm', variant = 'danger', action }) => {
        state.value = {
            open: true,
            title,
            message,
            confirmLabel,
            variant,
            action,
        };
    };

    const close = () => {
        state.value.open = false;
        state.value.action = null;
    };

    const confirm = () => {
        state.value.action?.();
        close();
    };

    return { state, ask, close, confirm };
}
