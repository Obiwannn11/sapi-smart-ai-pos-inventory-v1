/**
 * Cermin sisi-klien dari `App\Services\TaxCalculator` ([BL-065], diperluas
 * oleh [BL-097]).
 *
 * Ada dua salinan aturan pajak di proyek ini — satu PHP, satu JavaScript —
 * dan itu tidak bisa dihindari: kasir offline harus mencetak struk sebelum
 * server pernah melihat penjualannya. Yang bisa dihindari adalah salinan
 * KETIGA dan KEEMPAT, dan itulah alasan berkas ini ada: keranjang POS, struk
 * layar, dan struk termal semuanya membaca dari sini.
 *
 * Invarian yang sama dijaga di sini seperti di sisi PHP:
 *
 *     subtotal + biaya layanan + pajak = total, TEPAT.
 *
 * Kalau rumus di bawah menyimpang dari `TaxCalculator`, akibatnya bukan
 * angka yang beda sedikit di layar: penjualan offline akan tercatat
 * `needs_review` satu per satu saat sinkronisasi, karena server menghitung
 * ulang dan menemukan selisih. Ubah keduanya bersamaan, atau jangan ubah
 * sama sekali.
 *
 * Urutannya — biaya layanan lebih dulu, lalu pajak atas subtotal + biaya
 * layanan — mengikuti DPP PBJT ("jumlah pembayaran yang diterima penyedia",
 * UU HKPD Pasal 51). Alasan lengkapnya ada di docblock `TaxCalculator`.
 */

export const TAX_MODE_EXCLUSIVE = 'exclusive';
export const TAX_MODE_INCLUSIVE = 'inclusive';

/**
 * Rupiah tidak punya pecahan yang beredar — biaya layanan dan pajak
 * dibulatkan ke rupiah penuh dan angka keempat diturunkan dengan
 * pengurangan, sehingga penjumlahan di struk tidak pernah bisa meleset.
 */
const roundToRupiah = (value) => Math.round(value);

/**
 * Biaya layanan atas jumlah baris penjualan — selalu dari `base` apa adanya,
 * di kedua mode pajak. Cermin `TaxCalculator::serviceChargeOn()`.
 *
 * @param {number} base
 * @param {{enabled?: boolean, rate?: number}} service
 * @returns {number}
 */
function serviceChargeOn(base, service) {
    const rate = Number(service?.rate) || 0;

    if (!service?.enabled || rate <= 0) {
        return 0;
    }

    return roundToRupiah((base * rate) / 100);
}

/**
 * Urai satu angka dasar jadi empat angka uang.
 *
 * @param {number} base
 * @param {{enabled?: boolean, mode?: string, rate?: number}} context
 * @param {{enabled?: boolean, rate?: number, label?: string}} service
 * @returns {{subtotal: number, serviceCharge: number, tax: number, total: number}}
 */
export function applyTax(base, context = {}, service = {}) {
    const serviceCharge = serviceChargeOn(base, service);
    const rate = Number(context.rate) || 0;

    if (!context.enabled || rate <= 0) {
        return { subtotal: base, serviceCharge, tax: 0, total: base + serviceCharge };
    }

    if (context.mode === TAX_MODE_INCLUSIVE) {
        // rate/(100+rate), BUKAN rate/100 — harganya sudah mengandung pajak,
        // jadi yang dicari adalah bagian pajak DI DALAM angka itu.
        const total = base + serviceCharge;
        const tax = roundToRupiah((total * rate) / (100 + rate));

        return { subtotal: total - serviceCharge - tax, serviceCharge, tax, total };
    }

    // Dasar pengenaan pajak = subtotal + biaya layanan.
    const tax = roundToRupiah(((base + serviceCharge) * rate) / 100);

    return { subtotal: base, serviceCharge, tax, total: base + serviceCharge + tax };
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
        return {
            subtotal: 0,
            serviceCharge: 0,
            tax: 0,
            total: 0,
            label: null,
            rate: 0,
            mode: null,
            hasTax: false,
            serviceChargeLabel: null,
            serviceChargeRate: 0,
            hasServiceCharge: false,
        };
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
        serviceCharge: Number(transaction.service_charge_amount ?? 0),
        tax,
        total,
        label: transaction.tax_label ?? null,
        rate: Number(transaction.tax_rate ?? 0),
        mode: transaction.tax_mode ?? null,
        // Konteks beku yang ada, BUKAN pajak yang lebih besar dari nol:
        // penjualan bertarif yang kebetulan menghasilkan pajak nol karena
        // nilainya terlalu kecil tetap penjualan berpajak.
        hasTax: transaction.tax_mode != null,
        serviceChargeLabel: transaction.service_charge_label ?? null,
        serviceChargeRate: Number(transaction.service_charge_rate ?? 0),
        // Penandanya tarif beku, bukan nominalnya — alasan yang sama seperti
        // `hasTax` di atas.
        hasServiceCharge: transaction.service_charge_rate != null,
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

/**
 * Baris biaya layanan untuk dicetak, atau `null` bila tidak dipungut
 * ([BL-097]).
 *
 * Selalu `inline` — berbeda dari pajak, biaya layanan TIDAK pernah "sudah
 * termasuk di harga". Ia selalu ditambahkan di atas jumlah baris, jadi ia
 * selalu baris yang menaikkan total dan pelanggan berhak melihat nominalnya.
 *
 * Di mode pajak inclusive nominal ini adalah angka KOTOR (dihitung dari harga
 * katalog yang sudah mengandung pajak) sementara "Subtotal" tercetak bersih.
 * Itu konsekuensi yang disadari, bukan cacat — lihat docblock `TaxCalculator`.
 */
export function serviceChargeLine(totals) {
    if (!totals.hasServiceCharge) {
        return null;
    }

    const rate = String(totals.serviceChargeRate);
    const label = totals.serviceChargeLabel || 'Biaya Layanan';

    return { inline: true, text: `${label} ${rate}%`, amount: totals.serviceCharge };
}
