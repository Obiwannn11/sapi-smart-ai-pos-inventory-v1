/**
 * useOnlineStatus — reactive connectivity flag shared across the app.
 *
 * Singleton: the browser fires `online`/`offline` on window, so we listen once
 * at module scope and let every caller read the same ref.
 *
 * Caveat worth knowing: `navigator.onLine` only reports whether the device has
 * *a* network interface up — it says nothing about whether our server is
 * reachable (captive portals, dead uplink, server down all report "online").
 * Treat it as a hint for UI, never as proof a request will succeed. Code that
 * sends transactions must still fall back on an actual request failure, which
 * is why `markOffline()` exists.
 */

import { ref, readonly } from 'vue';

const isOnline = ref(typeof navigator === 'undefined' ? true : navigator.onLine !== false);

if (typeof window !== 'undefined') {
    window.addEventListener('online', () => {
        isOnline.value = true;
    });
    window.addEventListener('offline', () => {
        isOnline.value = false;
    });
}

/**
 * Force the flag offline after a request failed despite `navigator.onLine`
 * claiming otherwise. The next `online` event (or a successful request calling
 * `markOnline`) clears it.
 */
function markOffline() {
    isOnline.value = false;
}

function markOnline() {
    isOnline.value = true;
}

export function useOnlineStatus() {
    return {
        isOnline: readonly(isOnline),
        markOffline,
        markOnline,
    };
}
