<?php

/**
 * Nominal tunai tidak pernah diisi sendiri oleh modal pembayaran.
 *
 * Versi sebelumnya mengisikan sisa tagihan ke baris tunai begitu metodenya
 * dipilih. Uang tunai harus dihitung dulu di tangan kasir: angka "pas" yang
 * sudah terisi membuat Bayar bisa ditekan atas nominal yang belum pernah
 * diterima, dan selisihnya baru ketahuan saat laci ditutup. Yang otomatis
 * hanya baris NON-TUNAI — di sana nominalnya memang turunan, bukan uang yang
 * dihitung.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi tidak ada tes HTTP yang bisa
 * menangkap kemundurannya — dijaga langsung di sumbernya, mengikuti pola
 * `CartLineActionAffordanceTest`.
 */
function paymentModalSource(): string
{
    return file_get_contents(resource_path('js/Components/PaymentModal.vue'));
}

test('memilih metode tunai tidak mengisikan nominal apa pun', function () {
    $source = paymentModalSource();

    // Kaitnya dilepas seluruhnya: tidak ada lagi tempat untuk mengisi diam-diam
    // saat metodenya berganti.
    expect($source)->not->toContain('onMethodChange')
        ->and($source)->not->toContain('@change="onMethodChange');
});

test('hanya baris non-tunai yang boleh jadi penyeimbang otomatis', function () {
    $source = paymentModalSource();

    expect($source)->toContain('if (isNonCash(row.payment_method_id) && !row.touched) return i;');
});

test('tombol Uang Pas tetap ada supaya nominal pas cukup satu ketukan', function () {
    $source = paymentModalSource();

    expect($source)->toContain("'Sisa' : 'Uang Pas'")
        ->and($source)->toContain('fillRemainder(idx)');
});
