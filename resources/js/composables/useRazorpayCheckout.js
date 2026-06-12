import { router, usePage } from '@inertiajs/vue3';
import { useToast } from './useToast.js';

let activeCheckoutSubscriptionId = null;

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

export function checkoutFlowFinishedInUrl() {
    const checkout = new URLSearchParams(window.location.search).get('checkout');

    return checkout === 'success' || checkout === 'cancelled';
}

function submitVerifyForm(fields, csrfToken) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/settings/billing/razorpay/verify';

    Object.entries({ _token: csrfToken, ...fields }).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value ?? '';
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

export function useRazorpayCheckout() {
    const toast = useToast();
    const page = usePage();

    const open = async (session, {
        redirectOnSuccess = '/settings/billing?checkout=success&section=usage',
        redirectOnCancel = '/settings/billing?checkout=cancelled&section=plans',
    } = {}) => {
        if (checkoutFlowFinishedInUrl()) {
            return false;
        }

        const validationError = validateCheckoutSession(session);

        if (validationError) {
            toast.error(validationError);

            return false;
        }

        if (activeCheckoutSubscriptionId === session.subscription_id) {
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

        let completingPayment = false;
        const successRedirect = session.redirect_on_success ?? redirectOnSuccess;
        const cancelRedirect = session.redirect_on_cancel ?? redirectOnCancel;

        try {
            const rzp = new window.Razorpay({
                key: session.key,
                subscription_id: session.subscription_id,
                name: session.name,
                description: session.description,
                prefill: session.prefill ?? {},
                theme: session.theme ?? { color: '#2563eb' },
                handler(response) {
                    completingPayment = true;

                    if (! response?.razorpay_payment_id || ! response?.razorpay_subscription_id || ! response?.razorpay_signature) {
                        completingPayment = false;
                        activeCheckoutSubscriptionId = null;
                        toast.error('Payment completed but verification details were missing. Please contact support if you were charged.');

                        return;
                    }

                    try {
                        rzp.close();
                    } catch {
                        // Modal may already be closing.
                    }

                    submitVerifyForm({
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_subscription_id: response.razorpay_subscription_id,
                        razorpay_signature: response.razorpay_signature,
                        redirect: successRedirect,
                    }, page.props.csrf_token);
                },
                modal: {
                    ondismiss() {
                        if (completingPayment) {
                            return;
                        }

                        activeCheckoutSubscriptionId = null;
                        router.get(cancelRedirect, {}, { preserveScroll: true });
                    },
                },
            });

            rzp.on('payment.failed', () => {
                activeCheckoutSubscriptionId = null;
                toast.error('Payment failed. Please try again or use a different method.');
            });

            activeCheckoutSubscriptionId = session.subscription_id;
            rzp.open();

            return true;
        } catch {
            activeCheckoutSubscriptionId = null;
            toast.error('Unable to open Razorpay checkout. Please try again.');

            return false;
        }
    };

    return { open };
}
