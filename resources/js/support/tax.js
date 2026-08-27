/**
 * Cermin sisi-klien dari `App\Services\TaxCalculator` ([BL-065]).
 *
 * Ada dua salinan aturan pajak di proyek ini — satu PHP, satu JavaScript —
 * dan itu tidak bisa dihindari: kasir offline harus mencetak struk sebelum
 * server pernah melihat penjualannya. Yang bisa dihindari adalah salinan
 * KETIGA dan KEEMPAT, dan itulah alasan berkas ini ada: keranjang POS, struk
 * layar, dan struk termal semuanya membaca dari sini.
 *
 * Invarian yang sama dijaga di sini seperti di sisi PHP:
 *
 *     subtotal + pajak = total, TEPAT.
 *
 * Kalau rumus di bawah menyimpang dari `TaxCalculator`, akibatnya bukan
 * angka yang beda sedikit di layar: penjualan offline akan tercatat
 * `needs_review` satu per satu saat sinkronisasi, karena server menghitung
 * ulang dan menemukan selisih. Ubah keduanya bersamaan, atau jangan ubah
 * sama sekali.
 */

export const TAX_MODE_EXCLUSIVE = 'exclusive';
export const TAX_MODE_INCLUSIVE = 'inclusive';

/**
 * Rupiah tidak punya pecahan yang beredar — pajak dibulatkan ke rupiah
 * penuh dan angka ketiga diturunkan dengan pengurangan, sehingga
 * penjumlahan di struk tidak pernah bisa meleset.
 */
const roundToRupiah = (value) => Math.round(value);

/**
 * Urai satu angka dasar jadi tiga angka uang.
 *
 * @param {number} base
 * @param {{enabled?: boolean, mode?: string, rate?: number}} context
 * @returns {{subtotal: number, tax: number, total: number}}
 */
export function applyTax(base, context = {}) {
    const rate = Number(context.rate) || 0;

    if (!context.enabled || rate <= 0) {
        return { subtotal: base, tax: 0, total: base };
    }

    if (context.mode === TAX_MODE_INCLUSIVE) {
        // rate/(100+rate), BUKAN rate/100 — harganya sudah mengandung pajak,
        // jadi yang dicari adalah bagian pajak DI DALAM angka itu.
        const tax = roundToRupiah((base * rate) / (100 + rate));

        return { subtotal: base - tax, tax, total: base };
    }

    const tax = roundToRupiah((base * rate) / 100);

    return { subtotal: base, tax, total: base + tax };
}

/**
 * Angka-angka struk untuk satu transaksi yang SUDAH tersimpan.
 *
 * Membaca kolom yang dibekukan server, tidak menghitung ulang apa pun —
 * struk yang dicetak ulang harus sama persis dengan kertas yang dipegang
 * pelanggan, walau tarifnya sudah berubah sejak itu.
 *
 * Menjumlahkan baris item TIDAK boleh dipakai sebagai sumber subtotal: di
 * mode inclusive `unit_price` sudah mengandung pajak, sehingga jumlah baris
 * adalah TOTAL, bukan subtotal. Penjumlahan itu hanya dipakai sebagai
 * cadangan untuk transaksi yang objeknya belum membawa kolom barunya.
 */
export function receiptTotals(transaction) {
    if (!transaction) {
        return { subtotal: 0, tax: 0, total: 0, label: null, rate: 0, mode: null, hasTax: false };
    }

    const items = transaction.items || [];
    const itemsSum = items.reduce((sum, item) => sum + Number(item.subtotal || 0), 0);

    const total = Number(transaction.total_amount ?? itemsSum);
    const tax = Number(transaction.tax_amount ?? 0);
    const subtotal = transaction.subtotal_amount != null
        ? Number(transaction.subtotal_amount)
        : itemsSum;

    return {
        subtotal,
        tax,
        total,
        label: transaction.tax_label ?? null,
        rate: Number(transaction.tax_rate ?? 0),
        mode: transaction.tax_mode ?? null,
        // Konteks beku yang ada, BUKAN pajak yang lebih besar dari nol:
        // penjualan bertarif yang kebetulan menghasilkan pajak nol karena
        // nilainya terlalu kecil tetap penjualan berpajak.
        hasTax: transaction.tax_mode != null,
    };
}

/**
 * Baris pajak untuk dicetak, atau `null` bila tidak ada pajaknya.
 *
 * Bentuknya berbeda per mode karena yang perlu diketahui pelanggan berbeda:
 * di mode exclusive pajak adalah baris tambahan yang menaikkan total, di
 * mode inclusive ia keterangan bahwa total sudah mengandungnya.
 */
export function taxLine(totals) {
    if (!totals.hasTax) {
        return null;
    }

    // `Number` sudah membuang nol di belakang koma dari decimal:2 — 11.00
    // jadi "11", 11.50 jadi "11.5". Tarif bulat tidak perlu terlihat presisi.
    const rate = String(totals.rate);
    const label = totals.label || 'Pajak';

    if (totals.mode === TAX_MODE_INCLUSIVE) {
        return { inline: false, text: `Termasuk ${label} ${rate}%`, amount: totals.tax };
    }

    return { inline: true, text: `${label} ${rate}%`, amount: totals.tax };
}
