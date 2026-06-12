import { router } from '@inertiajs/vue3';

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

export function useRazorpayCheckout() {
    const open = async (session, { redirectOnSuccess = '/settings/billing?checkout=success&section=usage' } = {}) => {
        await loadCheckoutScript();

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
                    const cancelled = redirectOnSuccess.includes('subscription-required')
                        ? '/subscription-required?checkout=cancelled'
                        : '/settings/billing?checkout=cancelled&section=plans';

                    router.get(cancelled, {}, { preserveScroll: true });
                },
            },
        });

        rzp.open();
    };

    return { open };
}
