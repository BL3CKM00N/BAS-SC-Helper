// Live connection monitor. Shows an overlay when the device is offline or the server is
// unreachable and hides it again when things recover. It never reloads or navigates.
const CHECK_URL = '/up';
const LOGO_URL = document.querySelector('meta[name="app-logo"]')?.content || '/logo.svg';
const CHECK_TIMEOUT = 2500;
const IDLE_INTERVAL = 1000;
const HIDDEN_INTERVAL = 10000;
const DOWN_INTERVAL = 1000;
const RETRY_INTERVAL = 500;
const FAILS_BEFORE_SHOWING = 2;
const MIN_VISIBLE_MS = 1000;

const MESSAGES = {
    offline: {
        title: 'No internet connection',
        text: "Connect to Wi-Fi or mobile data to use BAS SC Helper. This screen disappears when you're back online.",
    },
    server: {
        title: "Can't reach the server",
        text: "The server isn't responding right now. We keep checking and will let you back in automatically.",
    },
};

let overlay = null;
let titleEl = null;
let textEl = null;
let failures = 0;
let shownAt = 0;
let hideTimer = null;
let checkTimer = null;
let checking = false;

function buildOverlay() {
    overlay = document.createElement('div');
    overlay.className = 'conn-overlay';
    overlay.setAttribute('role', 'alert');
    overlay.setAttribute('aria-live', 'assertive');
    overlay.hidden = true;
    overlay.innerHTML = `
        <div class="conn-card">
            <img src="${LOGO_URL}" alt="BAS World" class="conn-logo">
            <div class="conn-spinner" aria-hidden="true"></div>
            <h2 class="conn-title"></h2>
            <p class="conn-text"></p>
        </div>`;
    titleEl = overlay.querySelector('.conn-title');
    textEl = overlay.querySelector('.conn-text');
    document.body.appendChild(overlay);
}

function show(kind) {
    if (!overlay) buildOverlay();
    clearTimeout(hideTimer);
    titleEl.textContent = MESSAGES[kind].title;
    textEl.textContent = MESSAGES[kind].text;

    if (overlay.hidden) {
        shownAt = Date.now();
        overlay.hidden = false;
        requestAnimationFrame(() => overlay.classList.add('is-visible'));
    }
}

function hide() {
    if (!overlay || overlay.hidden) return;
    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => {
        overlay.classList.remove('is-visible');
        setTimeout(() => {
            if (!overlay.classList.contains('is-visible')) overlay.hidden = true;
        }, 250);
    }, Math.max(0, MIN_VISIBLE_MS - (Date.now() - shownAt)));
}

async function ping() {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), CHECK_TIMEOUT);

    try {
        const response = await fetch(`${CHECK_URL}?_=${Date.now()}`, { cache: 'no-store', signal: controller.signal });
        return response.ok;
    } catch (error) {
        return false;
    } finally {
        clearTimeout(timer);
    }
}

function schedule(delay) {
    clearTimeout(checkTimer);
    // Background tabs check less often; they check again as soon as they become visible.
    checkTimer = setTimeout(check, document.hidden ? Math.max(delay, HIDDEN_INTERVAL) : delay);
}

async function check() {
    if (checking) return;
    checking = true;

    const ok = await ping();
    checking = false;

    if (ok) {
        failures = 0;
        hide();
        schedule(IDLE_INTERVAL);
        return;
    }

    failures += 1;

    if (failures >= FAILS_BEFORE_SHOWING) {
        show(navigator.onLine ? 'server' : 'offline');
        schedule(DOWN_INTERVAL);
    } else {
        schedule(RETRY_INTERVAL);
    }
}

window.addEventListener('online', () => schedule(300));
window.addEventListener('offline', () => schedule(300));
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') schedule(300);
});

// Warm the logo so the overlay can show it even when the server is gone.
new Image().src = LOGO_URL;

schedule(IDLE_INTERVAL);
