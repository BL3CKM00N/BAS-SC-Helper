// Reminders only reach you when notifications are on, so gently point it out when this device has them off:
// a dot on the profile button, an "Off here" label in the menu and a dismissible hint on the dashboard.
const dots = document.querySelectorAll('[data-notify-dot]');
const badges = document.querySelectorAll('[data-notify-badge]');
const hint = document.querySelector('[data-notify-hint]');
const KEY = 'bas-notify-hint-dismissed';

const publicKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
const canAsk = Boolean(publicKey) && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

function dismissed() {
    try {
        return localStorage.getItem(KEY) === '1';
    } catch (error) {
        return false;
    }
}

function show(off) {
    dots.forEach((dot) => { dot.hidden = !off; });
    badges.forEach((badge) => { badge.hidden = !off; });

    if (hint) hint.hidden = !off || dismissed();
}

async function evaluate() {
    // Nothing to nudge when notifications cannot be turned on from here, or the person blocked them on purpose.
    if (!canAsk || Notification.permission === 'denied') return show(false);

    try {
        const registration = await navigator.serviceWorker.ready;

        show(!(await registration.pushManager.getSubscription()));
    } catch (error) {
        show(false);
    }
}

hint?.querySelector('[data-notify-hint-dismiss]')?.addEventListener('click', () => {
    try {
        localStorage.setItem(KEY, '1');
    } catch (error) {
        // Without storage the hint simply comes back next time.
    }

    hint.hidden = true;
});

// The notifications page tells us when this device switches notifications on or off.
window.addEventListener('push:changed', evaluate);

if (dots.length || badges.length || hint) evaluate();
