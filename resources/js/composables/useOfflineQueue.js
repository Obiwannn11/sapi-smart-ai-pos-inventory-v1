/**
 * useOfflineQueue — the outbox for sales rung up while the device was offline.
 *
 * These records are money that already changed hands but hasn't reached the
 * server. Everything here is built around not losing them:
 *
 *   Attribution guard. The server credits a synced sale to the *authenticated*
 *   user, so if cashier B flushed cashier A's queue, A's sales would land under
 *   B's name and B's shift report. Each row therefore stores the cashier (and
 *   tenant) that created it, and we only ever send rows belonging to whoever is
 *   logged in now. A's rows simply wait for A to come back.
 *
 *   No persisted "syncing" state. Marking rows in-flight on disk means a crash
 *   or a closed lid strands them in that state forever. We hold an in-memory
 *   lock instead and let `client_uuid` make a re-send harmless — the server
 *   dedups, so the worst case of an interrupted flush is sending twice.
 *
 *   Bounded retries. A row the server will never accept (deleted product,
 *   impossible timestamp) must not retry forever; after MAX_ATTEMPTS it is
 *   parked as `failed` and surfaced to a human rather than silently spinning.
 *
 * Rows are never deleted on logout — see services/offlineSession.js.
 */

import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    OUTBOX_STORE,
    readAll,
    writeRecord,
    deleteRecord,
    isOfflineStorageSupported,
} from '@/services/offlineDb';
import { getDeviceId } from '@/services/deviceId';

const MAX_ATTEMPTS = 5;

/** Keep in step with SyncOfflineTransactionsRequest::MAX_BATCH_SIZE. */
const BATCH_SIZE = 50;

const STATUS_QUEUED = 'queued';
const STATUS_FAILED = 'failed';

// Shared across every component that opens the queue.
const entries = ref([]);
const flushing = ref(false);
const lastResult = ref(null);

// In-memory only: see the note about not persisting "syncing".
let flushLock = false;

export function useOfflineQueue() {
    const page = usePage();

    const currentCashierId = computed(() => page.props.auth?.user?.id ?? null);
    const currentTenantId = computed(() => page.props.auth?.user?.tenant_id ?? null);

    /**
     * Rows this cashier is responsible for. Another cashier's rows stay in the
     * database but are invisible here — they are not ours to send or to count.
     */
    const ownEntries = computed(() =>
        entries.value.filter((e) => e.cashier_id === currentCashierId.value)
    );

    const pending = computed(() => ownEntries.value.filter((e) => e.status === STATUS_QUEUED));
    const failed = computed(() => ownEntries.value.filter((e) => e.status === STATUS_FAILED));

    const pendingCount = computed(() => pending.value.length);
    const failedCount = computed(() => failed.value.length);
    const hasUnsynced = computed(() => pendingCount.value > 0 || failedCount.value > 0);

    async function refresh() {
        entries.value = await readAll(OUTBOX_STORE);

        return entries.value;
    }

    /**
     * Queue a sale captured offline.
     *
     * @returns {Promise<boolean>} false when storage is unavailable — the caller
     *   MUST treat that as "the sale was not saved" and keep the cart.
     */
    async function enqueue({
        clientUuid,
        items,
        payments,
        totalAmount,
        notes = null,
        upsellEvents = [],
        customerName = null,
        tableNumber = null,
    }) {
        if (!isOfflineStorageSupported()) return false;

        const record = {
            client_uuid: clientUuid,
            cashier_id: currentCashierId.value,
            tenant_id: currentTenantId.value,
            device_id: getDeviceId(),
            // The device clock is the only clock we have offline. The server
            // sanity-checks it and rejects impossible values.
            occurred_at: new Date().toISOString(),
            items: toPlain(items),
            payments: toPlain(payments),
            total_amount: totalAmount,
            notes,
            // Identitas pesanan ikut menumpang baris ini, dengan alasan yang
            // sama seperti upsell di bawah: tanpa itu, pesanan yang sudah
            // dinamai kasir kehilangan namanya begitu tersinkron.
            customer_name: customerName,
            table_number: tableNumber,
            // Nasib saran upsell menumpang baris ini. Tanpa itu, periode offline
            // akan terlihat seolah tidak ada upsell sama sekali.
            upsell_events: toPlain(upsellEvents ?? []),
            status: STATUS_QUEUED,
            attempts: 0,
            last_error: null,
            queued_at: new Date().toISOString(),
        };

        const ok = await writeRecord(OUTBOX_STORE, record);
        if (ok) {
            await refresh();
        }

        return ok;
    }

    /**
     * Send this cashier's queued rows to the server and reconcile the outbox
     * against the per-row results.
     *
     * Safe to call speculatively (on reconnect, on an interval, from a button):
     * it no-ops when offline, already running, or empty.
     */
    async function flush() {
        if (flushLock || !navigator.onLine) return null;

        await refresh();

        const batch = pending.value.slice(0, BATCH_SIZE);
        if (batch.length === 0) return null;

        flushLock = true;
        flushing.value = true;

        try {
            const response = await fetch('/cashier/transactions/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ transactions: batch.map(toPayload) }),
            });

            // Session gone (often because the page came from the SW cache and the
            // token outlived the session). Not the rows' fault — leave them
            // queued, do not burn an attempt, and let the cashier re-login.
            if (response.status === 419 || response.status === 401) {
                lastResult.value = { ok: false, reason: 'auth' };

                return lastResult.value;
            }

            if (!response.ok) {
                await Promise.all(batch.map((entry) => markAttemptFailed(entry, `HTTP ${response.status}`)));
                lastResult.value = { ok: false, reason: 'server' };

                return lastResult.value;
            }

            const body = await response.json();
            await reconcile(batch, body.results ?? []);

            lastResult.value = {
                ok: true,
                synced: body.synced ?? 0,
                failed: body.failed ?? 0,
            };

            return lastResult.value;
        } catch (err) {
            // Network died mid-flush. Rows stay queued; client_uuid makes the
            // retry safe even if the server did process them.
            console.warn('[offlineQueue] flush failed:', err);
            lastResult.value = { ok: false, reason: 'network' };

            return lastResult.value;
        } finally {
            flushLock = false;
            flushing.value = false;
            await refresh();
        }
    }

    /**
     * Apply the server's per-row verdicts.
     *
     * `synced` and `duplicate` both mean the server holds the sale — drop the
     * row either way. `duplicate` is the expected outcome when a previous
     * flush's response was lost, not an error.
     */
    async function reconcile(batch, results) {
        const byUuid = new Map(results.map((r) => [r.client_uuid, r]));

        for (const entry of batch) {
            const result = byUuid.get(entry.client_uuid);

            if (!result) {
                await markAttemptFailed(entry, 'Tidak ada hasil dari server.');
                continue;
            }

            if (result.status === 'synced' || result.status === 'duplicate') {
                await deleteRecord(OUTBOX_STORE, entry.client_uuid);
                continue;
            }

            await markAttemptFailed(entry, result.message ?? 'Sinkronisasi gagal.');
        }
    }

    async function markAttemptFailed(entry, message) {
        const attempts = (entry.attempts ?? 0) + 1;

        await writeRecord(OUTBOX_STORE, {
            ...entry,
            attempts,
            last_error: message,
            status: attempts >= MAX_ATTEMPTS ? STATUS_FAILED : STATUS_QUEUED,
        });
    }

    /**
     * Put a parked row back in line — for when the owner has fixed whatever the
     * server was rejecting (restored a product, corrected a price).
     */
    async function retryFailed() {
        for (const entry of failed.value) {
            await writeRecord(OUTBOX_STORE, {
                ...entry,
                attempts: 0,
                last_error: null,
                status: STATUS_QUEUED,
            });
        }

        await refresh();

        return flush();
    }

    return {
        entries: ownEntries,
        pending,
        failed,
        pendingCount,
        failedCount,
        hasUnsynced,
        flushing,
        lastResult,
        maxAttempts: MAX_ATTEMPTS,
        enqueue,
        flush,
        refresh,
        retryFailed,
    };
}

/**
 * Strip queue bookkeeping — the server only wants the transaction itself.
 */
function toPayload(entry) {
    return {
        client_uuid: entry.client_uuid,
        occurred_at: entry.occurred_at,
        device_id: entry.device_id,
        total_amount: entry.total_amount,
        notes: entry.notes,
        items: entry.items,
        payments: entry.payments,
        // Baris lama tidak punya field-field di bawah ini; `?? null` menjaga
        // outbox yang sudah terisi sebelum fitur ini ada tetap bisa di-flush.
        customer_name: entry.customer_name ?? null,
        table_number: entry.table_number ?? null,
        upsell_events: entry.upsell_events ?? [],
    };
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/** Vue proxies cannot be structured-cloned into IndexedDB. */
function toPlain(value) {
    return JSON.parse(JSON.stringify(value));
}
