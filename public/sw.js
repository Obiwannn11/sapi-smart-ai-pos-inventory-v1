/**
 * SAPI POS — Service Worker
 *
 * Scope: "/" (served from public root so it can control the whole app).
 * Strategy:
 *   - Precache a minimal app shell (offline page, logo, manifest, icons).
 *   - Cache-first for hashed build assets under /build/assets/** (immutable).
 *   - Network-first for navigations, falling back to /offline.html when offline.
 *   - Never touch non-GET requests (POST/PUT/etc. always hit the network).
 *
 * NOTE: this is a hand-written SW (not Workbox). Bump CACHE_VERSION to force a
 * refresh of the precached shell when these files change.
 */

const CACHE_VERSION = 'v1';
const SHELL_CACHE = `sapi-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `sapi-assets-${CACHE_VERSION}`;

const SHELL_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/sapi-logo.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL_ASSETS)),
    );
    // Activate this SW immediately once installed.
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => ![SHELL_CACHE, ASSET_CACHE].includes(key))
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only handle GET; let the browser deal with POST/checkout/etc. directly.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Only handle same-origin requests (skip fonts.googleapis, APIs on other hosts).
    if (url.origin !== self.location.origin) return;

    // Immutable, content-hashed build assets → cache-first.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // Page navigations → network-first with offline fallback.
    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response && response.status === 200) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        return cached || Response.error();
    }
}

async function networkFirstNavigation(request) {
    try {
        return await fetch(request);
    } catch (err) {
        const cache = await caches.open(SHELL_CACHE);
        const offline = await cache.match('/offline.html');
        return offline || Response.error();
    }
}
