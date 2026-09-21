/**
 * Potongan per baris struk ([BL-103] butir 2a).
 *
 * Satu sumber untuk struk layar (`ReceiptModal.vue`) dan struk termal
 * (`services/escpos.js`), dengan alasan yang sama seperti `support/tax.js`:
 * layar dan kertas yang menulis potongan dengan cara berbeda adalah struk
 * yang tidak bisa dicocokkan pelanggan.
 *
 * Yang dibaca adalah kolom yang dibekukan server saat penjualan
 * (`original_unit_price`, dan `discount_amount` yang berarti potongan PER
 * UNIT). Tidak ada yang dihitung ulang dari harga katalog hari ini: struk yang
 * dicetak ulang minggu depan harus sama dengan kertas yang dibawa pulang.
 *
 * `discount_reason` sengaja TIDAK dicetak. Ia ditulis untuk owner dan kasir —
 * alasan aturan diskon, atau alasan harga khusus owner — bukan untuk
 * pelanggan. Yang pelanggan butuhkan hanyalah bahwa ada potongan, dan berapa.
 */

export const DISCOUNT_LABEL = 'Diskon';

const toRupiahCents = (value) => Math.round(value * 100) / 100;

/**
 * Potongan pada satu baris, atau `null` bila baris itu dibayar harga normal.
 *
 * Baris tanpa `original_unit_price` juga `null`, walau harganya lebih murah
 * dari katalog: penjualan offline hari ini tidak menyimpan jejak potongannya
 * (`[BL-115]`), dan struk tidak boleh mengarang potongan yang tidak tercatat.
 *
 * `grossSubtotal` adalah jumlah baris SEBELUM dipotong, termasuk modifier —
 * angka yang dicetak di sebelah "qty × harga normal", supaya kolom kanan bisa
 * dijumlahkan menurun: harga normal, lalu potongannya, sama dengan yang
 * dibayar.
 *
 * @param {object} item  satu `transaction_items` sebagaimana diserialkan server
 * @returns {{label: string, originalUnitPrice: number, amount: number, grossSubtotal: number} | null}
 */
export function itemDiscount(item) {
    const perUnit = Number(item?.discount_amount ?? 0);

    if (!(perUnit > 0) || item?.original_unit_price == null) {
        return null;
    }

    const amount = toRupiahCents(perUnit * Number(item.qty ?? 0));

    return {
        label: DISCOUNT_LABEL,
        originalUnitPrice: Number(item.original_unit_price),
        amount,
        grossSubtotal: toRupiahCents(Number(item.subtotal ?? 0) + amount),
    };
}

/**
 * Jumlah seluruh potongan pada satu transaksi — angka "Anda hemat".
 *
 * Bukan baris hitungan: subtotal yang tercetak sudah bersih dari potongan,
 * jadi angka ini keterangan, tidak dikurangkan lagi dari apa pun.
 *
 * @param {object} transaction
 * @returns {number}
 */
export function receiptSavings(transaction) {
    const total = (transaction?.items || []).reduce(
        (sum, item) => sum + (itemDiscount(item)?.amount ?? 0),
        0,
    );

    return toRupiahCents(total);
}
