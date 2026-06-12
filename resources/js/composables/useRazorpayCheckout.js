import { router } from '@inertiajs/vue3';
import { useToast } from './useToast.js';

const loadCheckoutScript = () => new Promise((resolve, reject) => {
    if (window.Razorpay) {
        resolve();
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://checkout.razorpay.com/v1/checkout.js';
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Unable to load Razorpay checkout.'));
    document.body.appendChild(script);
});

function validateCheckoutSession(session) {
    if (! session?.key || ! session?.subscription_id) {
        return 'Checkout could not be started. Please try again or contact support.';
    }

    return null;
}

export function useRazorpayCheckout() {
    const toast = useToast();

    const open = async (session, {
        redirectOnSuccess = '/settings/billing?checkout=success&section=usage',
        redirectOnCancel = '/settings/billing?checkout=cancelled&section=plans',
    } = {}) => {
        const validationError = validateCheckoutSession(session);

        if (validationError) {
            toast.error(validationError);

            return false;
        }

        try {
            await loadCheckoutScript();
        } catch {
            toast.error('Unable to load Razorpay checkout. Check your connection and try again.');

            return false;
        }

        if (typeof window.Razorpay !== 'function') {
            toast.error('Unable to load Razorpay checkout. Please try again.');

            return false;
        }

        try {
            const rzp = new window.Razorpay({
                key: session.key,
                subscription_id: session.subscription_id,
                name: session.name,
                description: session.description,
                prefill: session.prefill ?? {},
                theme: session.theme ?? { color: '#2563eb' },
                handler(response) {
                    router.post('/settings/billing/razorpay/verify', {
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_subscription_id: response.razorpay_subscription_id,
                        razorpay_signature: response.razorpay_signature,
                        redirect: redirectOnSuccess,
                    });
                },
                modal: {
                    ondismiss() {
                        router.get(redirectOnCancel, {}, { preserveScroll: true });
                    },
                },
            });

            rzp.open();

            return true;
        } catch {
            toast.error('Unable to open Razorpay checkout. Please try again.');

            return false;
        }
    };

    return { open };
}
