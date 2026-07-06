/**
 * useInstallPrompt — capture the browser's `beforeinstallprompt` event so the UI
 * can offer a custom "Install App" button instead of relying on the browser's
 * hidden install affordance.
 *
 * Singleton: the event fires once at load, so we listen at module scope and
 * expose the captured prompt to any component.
 */

import { ref, computed } from 'vue';

const deferredPrompt = ref(null);
const installed = ref(false);

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt.value = e;
    });
    window.addEventListener('appinstalled', () => {
        installed.value = true;
        deferredPrompt.value = null;
    });
}

export function useInstallPrompt() {
    const canInstall = computed(() => !!deferredPrompt.value && !installed.value);

    async function promptInstall() {
        if (!deferredPrompt.value) return false;
        deferredPrompt.value.prompt();
        const { outcome } = await deferredPrompt.value.userChoice;
        deferredPrompt.value = null;
        return outcome === 'accepted';
    }

    return { canInstall, installed, promptInstall };
}
