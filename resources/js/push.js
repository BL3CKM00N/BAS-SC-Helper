// The "Notifications" switch on the settings page: subscribes this device to web push.
const panel = document.querySelector('[data-push-panel]');

if (panel) {
    const publicKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const status = panel.querySelector('[data-push-status]');
    const enable = panel.querySelector('[data-push-enable]');
    const disable = panel.querySelector('[data-push-disable]');
    const test = panel.querySelector('[data-push-test]');

    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    const toBytes = (base64) => {
        const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

        return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
    };

    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        credentials: 'same-origin',
        body: JSON.stringify(body ?? {}),
    });

    async function current() {
        const registration = await navigator.serviceWorker.ready;

        return registration.pushManager.getSubscription();
    }

    function show(text, state) {
        status.textContent = text;
        enable.hidden = state !== 'off';
        disable.hidden = state !== 'on';
        test.hidden = state !== 'on';
    }

    async function render() {
        window.dispatchEvent(new Event('push:changed'));

        if (!publicKey) return show('Notifications are not set up on the server yet.', 'none');

        if (!supported) {
            return show('This browser cannot show notifications here. On an iPhone, add the app to your home screen and open it from there.', 'none');
        }

        if (Notification.permission === 'denied') {
            return show('Notifications are blocked for this site. Allow them in your browser or phone settings.', 'none');
        }

        return (await current()) ? show('Notifications are on for this device.', 'on') : show('Notifications are off for this device.', 'off');
    }

    enable.addEventListener('click', async () => {
        enable.disabled = true;

        try {
            if ((await Notification.requestPermission()) === 'granted') {
                const registration = await navigator.serviceWorker.ready;
                const subscription = (await registration.pushManager.getSubscription())
                    ?? (await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toBytes(publicKey) }));

                await post('/devices/subscribe', { ...subscription.toJSON(), contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0] });
            }
        } catch (error) {
            status.textContent = 'Could not turn on notifications. Try again.';
        }

        enable.disabled = false;
        render();
    });

    disable.addEventListener('click', async () => {
        const subscription = await current();

        if (subscription) {
            await post('/devices/unsubscribe', { endpoint: subscription.endpoint });
            await subscription.unsubscribe();
        }

        render();
    });

    test.addEventListener('click', async () => {
        test.disabled = true;
        const response = await post('/devices/test');
        status.textContent = response.ok ? 'Test sent. It should arrive in a few seconds.' : 'Could not send the test.';
        test.disabled = false;
    });

    render();
}
