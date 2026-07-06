/**
 * useThermalPrinter — reactive facade over the printer transport + ESC/POS builder.
 *
 * Shared singleton state (module scope) so the setup modal and the receipt modal
 * see the same connection and config. Config is persisted per-device in
 * localStorage — the physical printer is bound to the device, not the tenant, so
 * this needs no backend/migration.
 */

import { reactive, computed, readonly } from 'vue';
import { buildReceipt, buildTestReceipt } from '@/services/escpos';
import {
    createTransport,
    isBluetoothSupported,
    isUsbSupported,
} from '@/services/printerTransport';

const STORAGE_KEY = 'sapi.printer.config';

const defaultConfig = () => ({
    transport: 'bluetooth', // 'bluetooth' | 'usb'
    paperWidth: 58, // 58 | 80
    header: '', // store name; falls back to tenant name at print time
    subheader: '',
    footer: 'Terima Kasih',
    deviceName: '', // last paired device label (for display only)
});

function loadConfig() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) return { ...defaultConfig(), ...JSON.parse(raw) };
    } catch {
        /* ignore malformed storage */
    }
    return defaultConfig();
}

// ── Singleton state ─────────────────────────────────────────────────────────
const state = reactive({
    config: loadConfig(),
    connected: false,
    busy: false,
    lastError: '',
});

let transport = null;

function persist() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state.config));
    } catch {
        /* storage may be unavailable (private mode) */
    }
}

export function useThermalPrinter() {
    const isSupported = computed(() =>
        state.config.transport === 'usb' ? isUsbSupported() : isBluetoothSupported(),
    );

    const supports = {
        bluetooth: isBluetoothSupported(),
        usb: isUsbSupported(),
    };

    const isConfigured = computed(() => !!state.config.deviceName);

    function saveConfig(patch) {
        Object.assign(state.config, patch);
        persist();
    }

    /**
     * Pair a printer: shows the browser chooser (must run in a user gesture),
     * opens the connection, and remembers the device label.
     */
    async function pair() {
        state.lastError = '';
        state.busy = true;
        try {
            transport = createTransport(state.config.transport);
            await transport.requestDevice();
            await transport.connect();
            state.connected = true;
            saveConfig({ deviceName: transport.info.name });
            return true;
        } catch (err) {
            state.lastError = err?.message || String(err);
            state.connected = false;
            throw err;
        } finally {
            state.busy = false;
        }
    }

    /** Reconnect to an already-permitted device (still needs a user gesture on some browsers). */
    async function connect() {
        if (state.connected && transport?.connected) return true;
        // No cached device handle → require pairing again.
        if (!transport) return pair();
        state.busy = true;
        try {
            await transport.connect();
            state.connected = true;
            return true;
        } catch (err) {
            state.lastError = err?.message || String(err);
            state.connected = false;
            throw err;
        } finally {
            state.busy = false;
        }
    }

    async function ensureConnected() {
        if (transport?.connected) {
            state.connected = true;
            return;
        }
        await connect();
    }

    async function sendBytes(bytes) {
        state.busy = true;
        state.lastError = '';
        try {
            await ensureConnected();
            await transport.write(bytes);
        } catch (err) {
            state.lastError = err?.message || String(err);
            state.connected = !!transport?.connected;
            throw err;
        } finally {
            state.busy = false;
        }
    }

    /**
     * Print a transaction receipt.
     * @param {object} transaction
     * @param {object} [opts]  e.g. { tenantName } used when no custom header set.
     */
    async function printReceipt(transaction, opts = {}) {
        const bytes = buildReceipt(transaction, {
            paperWidth: state.config.paperWidth,
            header: state.config.header || opts.tenantName || 'SAPI POS',
            subheader: state.config.subheader,
            footer: state.config.footer,
        });
        await sendBytes(bytes);
    }

    async function testPrint() {
        const bytes = buildTestReceipt({ paperWidth: state.config.paperWidth });
        await sendBytes(bytes);
    }

    async function disconnect() {
        try {
            if (transport) await transport.disconnect();
        } finally {
            state.connected = false;
        }
    }

    return {
        config: state.config,
        state: readonly(state),
        isSupported,
        supports,
        isConfigured,
        pair,
        connect,
        printReceipt,
        testPrint,
        disconnect,
        saveConfig,
    };
}
