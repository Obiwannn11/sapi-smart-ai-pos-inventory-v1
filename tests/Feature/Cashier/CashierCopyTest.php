<?php

/**
 * Teks yang dibaca kasir: pendek, satu bahasa per istilah, tanpa tanda pisah.
 *
 * Istilah Inggris yang memang umum di kasir (POS, Sync, Login, Void) boleh.
 * Yang dijaga di sini adalah campuran yang tidak konsisten di layar yang sama
 * ("Keranjang" di kepala, "Tambah ke Cart" di modal), istilah sistem yang
 * bocor ke kasir ("ditolak server", "kas negatif"), dan kalimat yang
 * disambung dengan tanda pisah.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi dijaga langsung di sumbernya,
 * mengikuti pola `UpsellCardPlacementTest`.
 */
function cashierCopySources(): array
{
    return [
        'POS.vue' => file_get_contents(resource_path('js/Pages/Cashier/POS.vue')),
        'PaymentModal.vue' => file_get_contents(resource_path('js/Components/PaymentModal.vue')),
        'UpsellStrip.vue' => file_get_contents(resource_path('js/Components/UpsellStrip.vue')),
        'CartItem.vue' => file_get_contents(resource_path('js/Components/CartItem.vue')),
        'ModifierModal.vue' => file_get_contents(resource_path('js/Components/ModifierModal.vue')),
        'CashierTopbar.vue' => file_get_contents(resource_path('js/Components/CashierTopbar.vue')),
    ];
}

test('teks kasir tidak lagi memakai istilah campuran dan istilah sistem', function (string $phrase) {
    foreach (cashierCopySources() as $file => $source) {
        expect($source)->not->toContain($phrase, "{$file} masih memuat \"{$phrase}\"");
    }
})->with([
    'Tambah ke Cart',
    'Split Pembayaran',
    'ditolak server',
    'stok indikatif',
    'kas negatif dan hanya pemilik',
    'lewati dulu',
    "'BAYAR'",
    '📝',
    'mesin kasir dipakai bergantian',
]);

test('teks kasir tidak disambung dengan tanda pisah', function (string $phrase) {
    foreach (cashierCopySources() as $file => $source) {
        expect($source)->not->toContain($phrase, "{$file} masih memuat \"{$phrase}\"");
    }
})->with([
    'Sesi berakhir —',
    'Jangan tutup halaman —',
    'Simpan pesanan tanpa bayar —',
    'Wajib — tanpa alasan',
    'total belanja — non-tunai',
    'Harga khusus — ',
    'Batalkan — barangnya',
    '— bisa pilih lebih dari 1',
]);

test('kartu saran tidak mengulang chip jenisnya sebagai catatan', function () {
    $source = cashierCopySources()['UpsellStrip.vue'];

    expect($source)->toContain('v-if="current.note && current.note !== toneFor(current.type).title"');
});

test('pesan flash POS tanpa tanda seru dan tanpa istilah open bill', function () {
    $source = file_get_contents(app_path('Http/Controllers/Cashier/POSController.php'));

    expect($source)->not->toContain('berhasil!')
        ->and($source)->not->toContain('Open bill %s')
        ->and($source)->not->toContain('berhasil dibayar!')
        ->and($source)->toContain('Tagihan %s disimpan.')
        ->and($source)->toContain('"Tagihan {$transaction->code} lunas."');
});
