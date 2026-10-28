// Service worker: shows a launch screen when the app opens and the offline screen when a page cannot be loaded.
// __VERSION__ is replaced by the server with a hash of the precached files (see routes/web.php),
// so a changed file gives a new worker and a new cache. The version in the URLs also keeps
// Cloudflare from handing out an outdated copy.
const VERSION = '__VERSION__';
// True only in local development (filled in by the server). The offline page then shows the dev markers.
const DEV = __DEV__;
const CACHE = `bas-${VERSION}`;
const PRECACHE = [
    `/offline.html?v=${VERSION}`,
    `/logo.svg?v=${VERSION}`,
    `/favicon.svg?v=${VERSION}`,
    `/icons/icon-192.png?v=${VERSION}`,
    ...(DEV ? [`/logo-dev.svg?v=${VERSION}`] : []),
];
const PRECACHE_PATHS = PRECACHE.map((url) => new URL(url, self.location.origin).pathname);
// A launch that gets no answer shows the offline screen after this long. The offline screen then keeps
// checking and reloads by itself, so a slow network recovers without a black screen in between.
const NAVIGATION_TIMEOUT = 4000;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

async function offlinePage() {
    const cached = await caches.match('/offline.html', { ignoreSearch: true });

    if (!cached || !DEV) return cached;

    const html = (await cached.text()).replace('data-app="bas-offline"', 'data-app="bas-offline" data-env="local"');

    return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

// Proxy and CDN error pages (not our own) mean the app server is unreachable.
const isGatewayError = (status) => [502, 503, 504].includes(status) || (status >= 520 && status <= 530);

const timeout = (ms) => new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), ms));

// Right after a cold start the radio of a phone may still be waking up and the first request fails at once.
// One quick retry hides that; page loads (GET) are safe to repeat.
async function fetchWithRetry(request) {
    if (request.method !== 'GET') return fetch(request);

    const retry = request.clone();

    try {
        return await fetch(request);
    } catch (error) {
        await new Promise((resolve) => setTimeout(resolve, 400));

        return fetch(retry);
    }
}

async function handleNavigation(request) {
    // Opening the app (its start URL) always answers at once with the launch screen, which checks the
    // connection itself and continues into the app. That way there is never a blank screen on launch.
    const { pathname, searchParams } = new URL(request.url);

    if (pathname === '/' && !searchParams.has('go')) {
        const page = await offlinePage();

        if (page) return page;
    }

    try {
        const response = await Promise.race([fetchWithRetry(request), timeout(NAVIGATION_TIMEOUT)]);

        if (isGatewayError(response.status)) {
            const body = await response.clone().text();

            if (!body.includes('data-app="bas"')) {
                return (await offlinePage()) || response;
            }
        }

        return response;
    } catch (error) {
        return (await offlinePage()) || Response.error();
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigation(request));
        return;
    }

    // Static assets the offline screens need are served from the cache first.
    if (request.method === 'GET' && PRECACHE_PATHS.includes(new URL(request.url).pathname)) {
        event.respondWith(caches.match(request, { ignoreSearch: true }).then((cached) => cached || fetch(request)));
    }
});

// Push notifications for due reminders. The Done and Snooze buttons call signed URLs from the payload.
self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = { body: event.data ? event.data.text() : '' };
    }

    const { title = 'BAS SC Helper', ...options } = data;

    event.waitUntil(self.registration.showNotification(title, {
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        ...options,
    }));
});

self.addEventListener('notificationclick', (event) => {
    const data = event.notification.data || {};
    const post = (url) => (url ? fetch(url, { method: 'POST' }).catch(() => {}) : Promise.resolve());

    event.notification.close();

    event.waitUntil((async () => {
        if (event.action === 'done') return post(data.doneUrl);
        if (event.action === 'snooze') return post(data.snoozeUrl);

        const target = data.url || '/reminders';
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            try {
                await client.navigate(target);
            } catch (error) {
                // Some clients cannot be navigated, focusing them is enough.
            }

            return client.focus();
        }

        return self.clients.openWindow(target);
    })());
});
