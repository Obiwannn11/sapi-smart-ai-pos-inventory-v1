/**
 * SAPI POS — Service Worker
 *
 * Scope: "/" (served from public root so it can control the whole app).
 * Strategy:
 *   - Precache a minimal app shell (offline page, manifest, icons).
 *   - Cache-first for hashed build assets under /build/assets/** (immutable).
 *   - Network-first for navigations, falling back to a cached copy for
 *     offline-capable routes, then to /offline.html.
 *   - Never touch non-GET requests (POST/PUT/etc. always hit the network).
 *
 * Offline transactions are deliberately NOT queued here. The app layer owns the
 * outbox (IndexedDB) so it keeps full control over idempotency and stock
 * conflicts — see docs/phases-2/PHASE-PWA_Offline-Transaction-Sync.md.
 *
 * PRIVACY: PAGE_CACHE stores authenticated HTML (Inertia props, CSRF token,
 * the cashier's name). It is per-device, not per-user, so it MUST be cleared on
 * logout — otherwise the next user on a shared till could open the POS offline
 * and see the previous user's cached page. The app posts CLEAR_PRIVATE_CACHES
 * before logging out. The IndexedDB outbox is intentionally NOT cleared there:
 * it holds un-synced sales that must survive a logout.
 *
 * NOTE: this is a hand-written SW (not Workbox). Bump CACHE_VERSION to force a
 * refresh of the precached shell when these files change.
 */

// v4 — `offline.html` mendapat tautan "Buka Kasir" (`[BL-096]`). Ia terdaftar
// di SHELL_ASSETS, dan shell hanya diprecache ulang saat versi ini berubah;
// tanpa naik versi, setiap pemasangan yang sudah ada akan terus menyajikan
// halaman buntu yang lama dan perbaikannya tidak pernah sampai ke siapa pun.
const CACHE_VERSION = 'v4';
const SHELL_CACHE = `sapi-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `sapi-assets-${CACHE_VERSION}`;
const PAGE_CACHE = `sapi-pages-${CACHE_VERSION}`;

/**
 * Keep this list to files the offline shell actually RENDERS. `cache.addAll()`
 * is all-or-nothing: one 404 here rejects the whole install and the app loses
 * its offline shell entirely, silently. `/sapi-logo.png` sat here until
 * `[BL-077]` — 92 KB precached on every install for an image no page has ever
 * displayed. `StaticAssetBudgetTest` now fails if an entry stops existing.
 */
const SHELL_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

/**
 * Routes whose last successful HTML response is kept so they still boot when
 * the network is gone. Keep this list tight — every entry is authenticated
 * HTML sitting on disk until logout.
 */
const OFFLINE_CAPABLE_ROUTES = ['/cashier/pos'];

function isOfflineCapableRoute(pathname) {
    return OFFLINE_CAPABLE_ROUTES.some(
        (route) => pathname === route || pathname === `${route}/`,
    );
}

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
                        .filter((key) => ![SHELL_CACHE, ASSET_CACHE, PAGE_CACHE].includes(key))
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
    const url = new URL(request.url);
    const offlineCapable = isOfflineCapableRoute(url.pathname);

    try {
        const response = await fetch(request);

        // Keep the latest good copy of offline-capable pages so they can boot
        // without the network. Only 200s — never cache a redirect to /login or
        // an error page, which would strand the cashier on a dead shell.
        if (offlineCapable && response && response.status === 200 && !response.redirected) {
            const cache = await caches.open(PAGE_CACHE);
            cache.put(request, response.clone());
        }

        return response;
    } catch (err) {
        if (offlineCapable) {
            const pages = await caches.open(PAGE_CACHE);
            // Match on URL only: ignore ?query so /cashier/pos?foo=1 still hits.
            const cached = await pages.match(request, { ignoreSearch: true });
            if (cached) return cached;
        }

        const shell = await caches.open(SHELL_CACHE);
        const offline = await shell.match('/offline.html');

        return offline || Response.error();
    }
}

/**
 * The app asks us to drop anything user-identifying before it logs out.
 * Deliberately scoped to PAGE_CACHE: shell/assets are public, and the
 * IndexedDB outbox (owned by the app) must survive to protect un-synced sales.
 */
self.addEventListener('message', (event) => {
    if (event.data?.type !== 'CLEAR_PRIVATE_CACHES') return;

    event.waitUntil(
        caches.delete(PAGE_CACHE).then(() => {
            // Let the page await completion before it POSTs /logout.
            event.ports?.[0]?.postMessage({ ok: true });
        }),
    );
});
