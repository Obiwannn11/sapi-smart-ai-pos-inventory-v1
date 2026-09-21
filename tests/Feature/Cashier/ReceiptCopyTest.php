<?php

/**
 * Teks struk: dibaca PELANGGAN, dan layar harus berbunyi sama dengan kertas.
 *
 * Dua sumbernya terpisah — `ReceiptModal.vue` menggambar struk di layar,
 * `escpos.js` menyusun byte untuk printer thermal — jadi kata yang diganti di
 * satu tempat mudah tertinggal di tempat lain. Berkas Vue dan JS tidak pernah
 * dirender PHP, jadi dijaga langsung di sumbernya, mengikuti pola
 * `CashierPagesCopyTest`.
 */
function receiptScreenSource(): string
{
    return file_get_contents(resource_path('js/Components/ReceiptModal.vue'));
}

function receiptPaperSource(): string
{
    return file_get_contents(resource_path('js/services/escpos.js'));
}

test('struk tidak menyebut kategori produk kami di kepala struk', function () {
    // "Point of Sale" bukan keterangan toko yang mencetak struknya. Toko yang
    // ingin menulis alamat punya kolom "Alamat atau telepon" di pengaturan.
    expect(receiptScreenSource())->not->toContain('Point of Sale')
        ->and(receiptPaperSource())->not->toContain('Point of Sale');
});

test('layar dan kertas memakai kata yang sama untuk uang kembali', function () {
    // Modal pembayaran dan layar konfirmasi menulis "Kembalian".
    expect(receiptScreenSource())->toContain('<span>Kembalian</span>')
        ->and(receiptPaperSource())->toContain("twoCols('Kembalian'")
        ->and(receiptPaperSource())->not->toContain("twoCols('Kembali'");
});

test('kalimat penutup struk sama persis di layar dan di kertas', function () {
    $sentence = 'Simpan struk sebagai bukti pembayaran';

    expect(receiptScreenSource())->toContain($sentence)
        ->and(receiptPaperSource())->toContain($sentence)
        ->and(receiptScreenSource())->not->toContain('Simpan struk ini sebagai');
});

test('dua tombol cetak tidak bernama sama', function () {
    $source = receiptScreenSource();

    expect($source)->not->toContain('Cetak Thermal')
        // Cadangan menyebut jalannya, dan kembali menyebut hasilnya saat ia
        // satu-satunya cara mencetak.
        ->and($source)->toContain("canThermal.value ? 'Cetak lewat Browser' : 'Cetak Struk'")
        ->and($source)->toContain('{{ browserPrintLabel }}');
});

test('pengaturan printer tidak memakai istilah konsol platform', function () {
    $source = file_get_contents(resource_path('js/Components/PrinterSetupModal.vue'));

    expect($source)->not->toContain('nama tenant')
        ->and($source)->not->toContain('Sub-judul')
        ->and($source)->toContain('Alamat atau telepon');
});
