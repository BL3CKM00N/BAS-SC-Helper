// Service worker: shows the offline screen when a page navigation cannot reach the server.
const CACHE = 'bas-v5';
const PRECACHE = ['/offline.html', '/logo.svg', '/favicon.svg', '/icons/icon-192.png'];
const NAVIGATION_TIMEOUT = 10000;

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

const offlinePage = () => caches.match('/offline.html');

// Proxy and CDN error pages (not our own) mean the app server is unreachable.
const isGatewayError = (status) => [502, 503, 504].includes(status) || (status >= 520 && status <= 530);

const timeout = (ms) => new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), ms));

async function handleNavigation(request) {
    try {
        const response = await Promise.race([fetch(request), timeout(NAVIGATION_TIMEOUT)]);

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
    if (request.method === 'GET' && PRECACHE.includes(new URL(request.url).pathname)) {
        event.respondWith(caches.match(request, { ignoreSearch: true }).then((cached) => cached || fetch(request)));
    }
});
