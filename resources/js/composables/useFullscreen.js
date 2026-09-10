/**
 * useFullscreen — toggle the whole cashier shell in and out of fullscreen.
 *
 * Singleton: `fullscreenchange` fires on `document`, so we listen once at module
 * scope and let every caller read the same ref. That matters here because the
 * topbar lives on all seven cashier pages and Inertia navigates without a
 * reload — enter fullscreen on POS and it survives the trip to Riwayat or Kas.
 *
 * Two caveats worth knowing:
 *
 * - Safari on iPhone has no Fullscreen API at all (iPadOS does), and an iframe
 *   without `allow="fullscreen"` reports `fullscreenEnabled === false`. Both are
 *   covered by `canFullscreen`; callers must hide the button rather than render
 *   one that does nothing.
 * - F11 puts the *browser* in fullscreen, which the API cannot see. After F11
 *   `document.fullscreenElement` stays null, so our icon will claim we are
 *   windowed while the screen is not. Nothing can be done about that from here;
 *   pressing the button still works, it just nests one fullscreen in another.
 */

import { ref, readonly } from 'vue';

const isFullscreen = ref(false);

const canFullscreen =
    typeof document !== 'undefined' &&
    typeof document.documentElement?.requestFullscreen === 'function' &&
    document.fullscreenEnabled !== false;

if (canFullscreen) {
    document.addEventListener('fullscreenchange', () => {
        isFullscreen.value = document.fullscreenElement !== null;
    });
}

/**
 * Enter fullscreen, or leave it if we are already there.
 *
 * Must be called straight from a user gesture — browsers reject the promise
 * otherwise. We never set `isFullscreen` by hand: the `fullscreenchange`
 * listener is the single source of truth, so a rejected request (or an exit via
 * ESC, which fires no click) leaves the icon honest.
 *
 * @return {Promise<boolean>} whether the request was accepted
 */
async function toggleFullscreen() {
    if (!canFullscreen) {
        return false;
    }

    try {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
        } else {
            await document.documentElement.requestFullscreen({ navigationUI: 'hide' });
        }
    } catch {
        return false;
    }

    return true;
}

export function useFullscreen() {
    return {
        isFullscreen: readonly(isFullscreen),
        canFullscreen,
        toggleFullscreen,
    };
}
