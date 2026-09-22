/**
 * Stripe Payment Element, themed with the design system's tokens so the card
 * form reads as part of the storefront rather than a bolted-on widget.
 *
 * Card details are entered inside Stripe's iframe and confirmed directly with
 * Stripe: they never touch our servers (PCI DSS, SAQ-A).
 *
 * Stripe.js is loaded only when a customer actually reaches payment, so
 * browsing the storefront makes no third-party request.
 */
const token = (name, fallback) =>
    getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

const loadStripeJs = () =>
    new Promise((resolve, reject) => {
        if (window.Stripe) {
            resolve(window.Stripe);

            return;
        }

        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.onload = () => resolve(window.Stripe);
        script.onerror = () => reject(new Error('Stripe.js failed to load.'));
        document.head.appendChild(script);
    });

const appearance = () => ({
    theme: 'none',
    variables: {
        colorPrimary: token('--color-gold', '#C9A24B'),
        colorBackground: token('--color-surface', '#FFFFFF'),
        colorText: token('--color-ink', '#24242A'),
        colorDanger: token('--color-status-required', '#C0392B'),
        fontFamily: "'Source Sans 3', Calibri, sans-serif",
        fontSizeBase: '14px',
        spacingUnit: '4px',
        borderRadius: '2px',
    },
    rules: {
        '.Input': {
            border: `1px solid ${token('--color-border-field', '#D9D2C4')}`,
            padding: '9px 11px',
            boxShadow: 'none',
        },
        '.Input:focus': {
            border: `1px solid ${token('--color-gold', '#C9A24B')}`,
            boxShadow: 'none',
            outline: 'none',
        },
        '.Label': {
            fontSize: '12.5px',
            fontWeight: '600',
            color: token('--color-ink', '#24242A'),
        },
    },
});

const mount = async ({ clientSecret, publishableKey }) => {
    const target = document.getElementById('payment-element');

    if (!target || !clientSecret || !publishableKey || target.dataset.mounted === 'true') {
        return;
    }

    const errorBox = document.getElementById('payment-error');

    let stripe;

    try {
        stripe = (await loadStripeJs())(publishableKey);
    } catch (error) {
        if (errorBox) errorBox.textContent = error.message;

        return;
    }

    const elements = stripe.elements({ clientSecret, appearance: appearance() });
    elements.create('payment', { layout: 'tabs' }).mount('#payment-element');
    target.dataset.mounted = 'true';

    document.getElementById('pay-button')?.addEventListener('click', async (event) => {
        event.preventDefault();

        const button = event.currentTarget;
        button.disabled = true;
        if (errorBox) errorBox.textContent = '';

        const { error, paymentIntent } = await stripe.confirmPayment({ elements, redirect: 'if_required' });

        if (error) {
            if (errorBox) errorBox.textContent = error.message;
            button.disabled = false;

            return;
        }

        if (paymentIntent?.status === 'succeeded') {
            // The server re-reads the intent from Stripe before recording it.
            window.Livewire.dispatch('confirm-payment', { intentId: paymentIntent.id });
        } else {
            if (errorBox) errorBox.textContent = 'The payment could not be completed. Please try another card.';
            button.disabled = false;
        }
    });
};

/**
 * Livewire tells us when an intent exists. Registered defensively: this bundle
 * and Livewire's script race, so "livewire:init" may already have fired by the
 * time this module runs — waiting only on the event silently does nothing.
 */
const listen = () => {
    window.Livewire.on('payment-ready', (payload) => {
        const detail = Array.isArray(payload) ? payload[0] : payload;

        // The element renders in the same round trip, so let the DOM settle.
        requestAnimationFrame(() => mount(detail));
    });
};

if (window.Livewire) {
    listen();
} else {
    document.addEventListener('livewire:init', listen, { once: true });
}

window.mountPaymentElement = (publishableKey, clientSecret) => mount({ publishableKey, clientSecret });
