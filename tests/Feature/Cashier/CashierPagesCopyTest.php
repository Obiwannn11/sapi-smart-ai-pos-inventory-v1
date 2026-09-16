<?php

/**
 * Teks halaman kasir selain POS, dan pesan kesalahan yang mendarat di layar
 * kasir sebagai flash.
 *
 * Yang dijaga: tanpa istilah basis data ("pending", "completed", "kas negatif"
 * kepada kasir), tanpa istilah Inggris yang punya padanan di layar sebelahnya
 * ("Expected Cash", "Closing Amount"), dan angka rupiah berformat Indonesia.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi dijaga langsung di sumbernya,
 * mengikuti pola `CashierCopyTest`.
 */
function cashierPageSources(): array
{
    $pages = ['CashDrawer', 'CashDrawerClose', 'CashDrawerSummary', 'Queue', 'TransactionHistory'];

    return array_combine($pages, array_map(
        fn (string $page) => file_get_contents(resource_path("js/Pages/Cashier/{$page}.vue")),
        $pages,
    ));
}

test('halaman kasir tidak memakai istilah Inggris yang sudah punya padanan di layar sebelahnya', function (string $phrase) {
    foreach (cashierPageSources() as $page => $source) {
        expect($source)->not->toContain($phrase, "{$page}.vue masih memuat \"{$phrase}\"");
    }
})->with([
    'Expected Cash',
    'Closing Amount',
    'Pendapatan per Metode Pembayaran',
    'Self-Order',
    'Cakupan: ',
    'anomali stok/harga',
    'Tidak ada transaksi ditemukan',
]);

test('rekap kas memakai nama angka yang sama dengan halaman tutup kas', function () {
    $summary = cashierPageSources()['CashDrawerSummary'];

    expect($summary)->toContain('Seharusnya di laci')
        ->and($summary)->toContain('Uang fisik aktual')
        // Judul dialognya "Keluar dari kasir?", jadi tombolnya ikut.
        ->and($summary)->toContain('Keluar
                    </button>');
});

test('pesan kesalahan kasir tidak memakai istilah basis data', function (string $phrase) {
    $sources = [
        'TransactionService' => file_get_contents(app_path('Services/TransactionService.php')),
        'FulfillmentService' => file_get_contents(app_path('Services/FulfillmentService.php')),
        'CashDrawerController' => file_get_contents(app_path('Http/Controllers/Cashier/CashDrawerController.php')),
    ];

    foreach ($sources as $name => $source) {
        expect($source)->not->toContain($phrase, "{$name} masih memuat \"{$phrase}\"");
    }
})->with([
    'User tidak terautentikasi',
    'bukan pending / sudah dibayar',
    'bukan open bill / sudah dibayar',
    'tercatat sebagai kas negatif',
    'melunasi kas negatif',
    'transaksi completed yang bisa di-void',
    'lantai margin',
    'fulfillment tracking',
    '(occurred_at)',
    'Mutasi kas dicatat',
    'Kas berhasil',
]);

test('pesan kurang bayar menulis rupiah dengan pemisah ribuan Indonesia', function () {
    $source = file_get_contents(app_path('Services/TransactionService.php'));

    // `number_format()` polos menulis "12,000" — dibaca kasir sebagai
    // dua belas koma nol.
    expect($source)->not->toContain('number_format($totalAmount).')
        ->and($source)->not->toContain('number_format($totalPaid)')
        ->and(substr_count($source, "number_format(\$totalAmount, 0, ',', '.')"))->toBe(2);
});

test('papan antrian tidak membocorkan nama status mentah', function () {
    $source = file_get_contents(app_path('Services/FulfillmentService.php'));

    expect($source)->not->toContain('(sekarang: ')
        ->and($source)->toContain('Status pesanan sudah diubah orang lain.');
});
