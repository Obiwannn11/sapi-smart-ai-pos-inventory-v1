/**
 * Thermal-printer transport layer.
 *
 * Two implementations behind one interface so the composable can treat them
 * interchangeably:
 *
 *   interface Transport {
 *     get info(): { name: string }        // human label for the paired device
 *     get connected(): boolean
 *     requestDevice(): Promise<void>       // shows the browser chooser (user gesture)
 *     connect(): Promise<void>             // open a session with the chosen device
 *     write(bytes: Uint8Array): Promise<void>
 *     disconnect(): Promise<void>
 *   }
 *
 * Both APIs (Web Bluetooth / Web USB) are Chromium-only and require a secure
 * context (HTTPS or localhost). Callers must check `isBluetoothSupported` /
 * `isUsbSupported` before offering the option.
 */

export const isBluetoothSupported = () =>
    typeof navigator !== 'undefined' && 'bluetooth' in navigator;

export const isUsbSupported = () =>
    typeof navigator !== 'undefined' && 'usb' in navigator;

// Common GATT services exposed by generic ESC/POS BLE printers. We filter on
// these when possible and fall back to acceptAllDevices + optionalServices.
const PRINTER_SERVICE_UUIDS = [
    0x18f0, // common on many 58mm BLE printers
    0xff00,
    0xff10,
    0xffe0,
    '49535343-fe7d-4ae5-8fa9-9fafd205e455', // ISSC / microchip transparent UART
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ── Web Bluetooth ─────────────────────────────────────────────────────────

export class BluetoothTransport {
    constructor() {
        this.device = null;
        this.characteristic = null;
    }

    get info() {
        return { name: this.device?.name || 'Bluetooth Printer' };
    }

    get connected() {
        return !!this.device?.gatt?.connected && !!this.characteristic;
    }

    async requestDevice() {
        if (!isBluetoothSupported()) {
            throw new Error('Web Bluetooth tidak didukung di browser ini.');
        }
        this.device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: PRINTER_SERVICE_UUIDS,
        });
        this.device.addEventListener('gattserverdisconnected', () => {
            this.characteristic = null;
        });
    }

    async connect() {
        if (!this.device) throw new Error('Belum ada printer Bluetooth yang dipilih.');
        const server = await this.device.gatt.connect();

        // Find the first service that exposes a writable characteristic.
        const services = await server.getPrimaryServices();
        for (const service of services) {
            const chars = await service.getCharacteristics();
            const writable = chars.find(
                (c) => c.properties.write || c.properties.writeWithoutResponse,
            );
            if (writable) {
                this.characteristic = writable;
                return;
            }
        }
        throw new Error('Tidak menemukan karakteristik tulis pada printer ini.');
    }

    async write(bytes) {
        if (!this.characteristic) throw new Error('Printer Bluetooth belum terhubung.');
        // BLE payloads are small; chunk to stay under the ATT MTU and pace writes.
        const CHUNK = 180;
        const useResponse = this.characteristic.properties.write;
        for (let i = 0; i < bytes.length; i += CHUNK) {
            const slice = bytes.slice(i, i + CHUNK);
            if (useResponse) {
                await this.characteristic.writeValueWithResponse(slice);
            } else {
                await this.characteristic.writeValueWithoutResponse(slice);
                await sleep(20); // give the printer buffer time to drain
            }
        }
    }

    async disconnect() {
        try {
            if (this.device?.gatt?.connected) this.device.gatt.disconnect();
        } finally {
            this.characteristic = null;
        }
    }
}

// ── Web USB ────────────────────────────────────────────────────────────────

export class UsbTransport {
    constructor() {
        this.device = null;
        this.endpoint = null; // bulk OUT endpoint number
    }

    get info() {
        const p = this.device;
        return { name: p ? p.productName || `USB ${p.vendorId}:${p.productId}` : 'USB Printer' };
    }

    get connected() {
        return !!this.device?.opened && this.endpoint != null;
    }

    async requestDevice() {
        if (!isUsbSupported()) {
            throw new Error('Web USB tidak didukung di browser ini.');
        }
        // Printer class code is 0x07; offer it as a hint but accept anything.
        this.device = await navigator.usb.requestDevice({
            filters: [{ classCode: 0x07 }, {}],
        });
    }

    async connect() {
        if (!this.device) throw new Error('Belum ada printer USB yang dipilih.');
        await this.device.open();
        if (this.device.configuration === null) {
            await this.device.selectConfiguration(1);
        }

        // Find an interface with a bulk OUT endpoint and claim it.
        for (const iface of this.device.configuration.interfaces) {
            const alt = iface.alternate;
            const out = alt.endpoints.find(
                (e) => e.direction === 'out' && e.type === 'bulk',
            );
            if (out) {
                await this.device.claimInterface(iface.interfaceNumber);
                this.interfaceNumber = iface.interfaceNumber;
                this.endpoint = out.endpointNumber;
                return;
            }
        }
        throw new Error('Tidak menemukan endpoint OUT pada printer USB ini.');
    }

    async write(bytes) {
        if (!this.connected) throw new Error('Printer USB belum terhubung.');
        await this.device.transferOut(this.endpoint, bytes);
    }

    async disconnect() {
        try {
            if (this.interfaceNumber != null) {
                await this.device.releaseInterface(this.interfaceNumber);
            }
            if (this.device?.opened) await this.device.close();
        } catch {
            /* ignore */
        } finally {
            this.endpoint = null;
        }
    }
}

/** Factory: returns a fresh transport instance for the given kind. */
export function createTransport(kind) {
    if (kind === 'usb') return new UsbTransport();
    return new BluetoothTransport();
}
