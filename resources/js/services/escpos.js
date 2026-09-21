/**
 * ESC/POS receipt builder.
 *
 * Pure, framework-agnostic: turns a transaction object (the same shape used by
 * ReceiptModal.vue) into a Uint8Array of ESC/POS bytes ready to send to a
 * thermal printer over Bluetooth or USB.
 *
 * Chars-per-line: 58mm paper ≈ 32 cols, 80mm paper ≈ 48 cols (Font A).
 */

import { BUSINESS_TZ } from '@/support/date';
import { itemDiscount, receiptSavings } from '@/support/discount';
import { receiptTotals, serviceChargeLine, taxLine } from '@/support/tax';

// ── Low-level command bytes ──────────────────────────────────────────────
const ESC = 0x1b;
const GS = 0x1d;

const CMD = {
    init: [ESC, 0x40], // ESC @  — reset printer
    alignLeft: [ESC, 0x61, 0], // ESC a 0
    alignCenter: [ESC, 0x61, 1], // ESC a 1
    alignRight: [ESC, 0x61, 2], // ESC a 2
    boldOn: [ESC, 0x45, 1], // ESC E 1
    boldOff: [ESC, 0x45, 0], // ESC E 0
    doubleOn: [GS, 0x21, 0x11], // GS ! — double width + height
    doubleOff: [GS, 0x21, 0x00],
    cut: [GS, 0x56, 66, 0], // GS V 66 0 — partial cut with feed
};

/** Collects command + text bytes, then emits a single Uint8Array. */
class Builder {
    constructor() {
        this.bytes = [];
    }

    raw(arr) {
        for (const b of arr) this.bytes.push(b & 0xff);
        return this;
    }

    /**
     * Append text as single-byte characters. Non-ASCII is transliterated where
     * we can (× → x) and otherwise clamped to a byte / replaced with '?' so the
     * printer never chokes on multi-byte UTF-8.
     */
    text(str) {
        const clean = String(str ?? '').replace(/×/g, 'x');
        for (let i = 0; i < clean.length; i++) {
            const code = clean.charCodeAt(i);
            this.bytes.push(code > 0xff ? 0x3f : code); // '?' for anything unmapped
        }
        return this;
    }

    line(str = '') {
        return this.text(str).raw([0x0a]);
    }

    feed(n = 1) {
        for (let i = 0; i < n; i++) this.bytes.push(0x0a);
        return this;
    }

    build() {
        return new Uint8Array(this.bytes);
    }
}

// ── Text layout helpers ──────────────────────────────────────────────────

/** Left/right columns padded to `width`; truncates the left side if needed. */
function twoCols(left, right, width) {
    left = String(left ?? '');
    right = String(right ?? '');
    const space = width - right.length;
    if (left.length > space - 1 && space > 1) {
        left = left.slice(0, Math.max(0, space - 1));
    }
    const gap = Math.max(1, width - left.length - right.length);
    return left + ' '.repeat(gap) + right;
}

function divider(width, char = '-') {
    return char.repeat(width);
}

function formatCurrency(value) {
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
}

function formatDate(date) {
    if (!date) return '';
    return new Date(date).toLocaleDateString('id-ID', {
        timeZone: BUSINESS_TZ,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatTime(date) {
    if (!date) return '';
    return new Date(date).toLocaleTimeString('id-ID', {
        timeZone: BUSINESS_TZ,
        hour: '2-digit',
        minute: '2-digit',
    });
}

// ── Public API ────────────────────────────────────────────────────────────

/**
 * Build ESC/POS bytes for a transaction receipt.
 *
 * @param {object} transaction  Same shape as ReceiptModal.vue expects.
 * @param {object} [options]
 * @param {58|80} [options.paperWidth=58]
 * @param {string} [options.header]  Store name / tenant name (centered, bold).
 * @param {string} [options.subheader]  Small line under the header (address, etc.).
 * @param {string} [options.footer]  Custom footer line.
 * @returns {Uint8Array}
 */
export function buildReceipt(transaction, options = {}) {
    const width = options.paperWidth === 80 ? 48 : 32;
    const header = options.header || 'SAPI POS';
    const footer = options.footer || 'Terima Kasih';

    const b = new Builder();
    b.raw(CMD.init);

    // ── Header ──
    b.raw(CMD.alignCenter).raw(CMD.boldOn).raw(CMD.doubleOn);
    b.line(header);
    b.raw(CMD.doubleOff);
    b.raw(CMD.boldOff);
    if (options.subheader) b.line(options.subheader);

    // ── Nomor antrian ──
    // Hanya ada saat papan dapur hidup atau mode identitas "kode panggil"
    // dipilih ([BL-026]), jadi struk outlet lain tidak berubah sama sekali.
    // Dicetak besar: seluruh gunanya bertumpu pada nomor ini bisa dibaca
    // pelanggan dari seberang meja lalu dipanggil.
    if (transaction.queue_number) {
        b.line(divider(width));
        b.raw(CMD.boldOn).raw(CMD.doubleOn);
        b.line(`NO. ANTRIAN ${transaction.queue_number}`);
        b.raw(CMD.doubleOff).raw(CMD.boldOff);
    }

    b.raw(CMD.alignLeft);
    b.line(divider(width));

    // ── Transaction info ──
    if (transaction.code) b.line(twoCols('No.', transaction.code, width));
    b.line(twoCols('Tanggal', formatDate(transaction.created_at), width));
    b.line(twoCols('Waktu', formatTime(transaction.created_at), width));
    if (transaction.user?.name) b.line(twoCols('Kasir', transaction.user.name, width));
    if (transaction.customer_name) b.line(twoCols('Pelanggan', transaction.customer_name, width));
    if (transaction.table_number) b.line(twoCols('Meja', transaction.table_number, width));
    b.line(divider(width));

    // ── Items ──
    const items = transaction.items || [];
    for (const item of items) {
        const discount = itemDiscount(item);

        b.line(item.variant_name || '-');

        // Baris berdiskon dicetak dengan harga NORMAL lalu potongannya
        // ([BL-103] butir 2a), supaya kolom kanan bisa dijumlahkan menurun.
        // Mencetak harga yang sudah dipotong saja membuat pelanggan melihat
        // angka yang lebih kecil dari papan menu tanpa tahu sebabnya.
        if (discount) {
            b.line(twoCols(`  ${item.qty} x ${formatCurrency(discount.originalUnitPrice)}`, formatCurrency(discount.grossSubtotal), width));
            b.line(twoCols(`  ${discount.label}`, `-${formatCurrency(discount.amount)}`, width));
        } else {
            b.line(twoCols(`  ${item.qty} x ${formatCurrency(item.unit_price)}`, formatCurrency(item.subtotal), width));
        }

        for (const mod of item.modifiers || []) {
            const extra = Number(mod.extra_price) > 0 ? formatCurrency(mod.extra_price) : '';
            b.line(twoCols(`  + ${mod.modifier_name}`, extra, width));
        }
        if (item.notes) b.line(`  * ${item.notes}`);
    }
    b.line(divider(width));

    // ── Totals ──
    //
    // Subtotal dibaca dari kolom transaksi, tidak dijumlahkan dari baris
    // item ([BL-065]): di mode pajak inclusive `unit_price` sudah mengandung
    // pajak, jadi jumlah baris adalah TOTAL. Kertas yang tidak bisa
    // dijumlahkan ulang oleh pelanggan adalah keluhan yang paling cepat
    // datang, dan di sini ia sudah terlanjur tercetak.
    const totals = receiptTotals(transaction);
    const tax = taxLine(totals);
    const serviceCharge = serviceChargeLine(totals);

    b.line(twoCols('Subtotal', formatCurrency(totals.subtotal), width));
    // Sebelum pajak, karena pajak dipungut ATAS jumlah keduanya ([BL-097]).
    if (serviceCharge) {
        b.line(twoCols(serviceCharge.text, formatCurrency(serviceCharge.amount), width));
    }
    if (tax && tax.inline) {
        b.line(twoCols(tax.text, formatCurrency(tax.amount), width));
    }
    b.raw(CMD.boldOn);
    b.line(twoCols('TOTAL', formatCurrency(totals.total), width));
    b.raw(CMD.boldOff);
    if (tax && !tax.inline) {
        b.line(twoCols(tax.text, formatCurrency(tax.amount), width));
    }
    // Keterangan, bukan baris hitungan: subtotal di atas sudah bersih dari
    // potongan, jadi angka ini tidak dikurangkan lagi dari apa pun.
    const savings = receiptSavings(transaction);
    if (savings > 0) {
        b.line(twoCols('Anda hemat', formatCurrency(savings), width));
    }
    b.line(divider(width));

    // ── Payments ──
    for (const p of transaction.payments || []) {
        const label = p.payment_method?.name || 'Pembayaran';
        b.line(twoCols(label, formatCurrency(p.amount), width));
    }
    if (Number(transaction.change_amount) > 0) {
        b.line(twoCols('Kembalian', formatCurrency(transaction.change_amount), width));
    }
    b.line(divider(width));

    // ── Notes ──
    if (transaction.notes) {
        b.raw(CMD.alignCenter).line(transaction.notes).raw(CMD.alignLeft);
    }

    // ── Footer ──
    b.raw(CMD.alignCenter);
    b.raw(CMD.boldOn).line(`*** ${footer} ***`).raw(CMD.boldOff);
    b.line('Simpan struk sebagai bukti pembayaran');
    b.raw(CMD.alignLeft);

    // Feed and cut.
    b.feed(4);
    b.raw(CMD.cut);

    return b.build();
}

/** A short self-test print to verify the printer + paper width alignment. */
export function buildTestReceipt(options = {}) {
    const width = options.paperWidth === 80 ? 48 : 32;
    const b = new Builder();
    b.raw(CMD.init);
    b.raw(CMD.alignCenter).raw(CMD.boldOn).raw(CMD.doubleOn);
    b.line('SAPI POS');
    b.raw(CMD.doubleOff).raw(CMD.boldOff);
    b.line('Tes Cetak Printer');
    b.raw(CMD.alignLeft);
    b.line(divider(width));
    b.line(twoCols('Lebar kertas', `${options.paperWidth === 80 ? 80 : 58}mm`, width));
    b.line(twoCols('Kolom', String(width), width));
    b.line(divider(width));
    b.line('1234567890'.repeat(Math.ceil(width / 10)).slice(0, width));
    b.line('Aa Bb Cc Dd Ee Ff Gg Hh Ii Jj');
    b.raw(CMD.alignCenter).line('Jika baris di atas rapi,').line('printer siap dipakai.').raw(CMD.alignLeft);
    b.feed(4);
    b.raw(CMD.cut);
    return b.build();
}
