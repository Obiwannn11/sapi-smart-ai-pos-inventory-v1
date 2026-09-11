<?php

/**
 * Aksi baris keranjang harus terbaca sebagai tombol di layar sentuh.
 *
 * Kasir bekerja di tablet: tidak ada kursor yang bisa melayang, jadi bentuk
 * tombol yang hanya lahir dari `hover:` bagi kasir tidak pernah ada. Tiga hal
 * yang dijaga di sini, ketiganya pernah salah:
 *
 * 1. Kotak, garis tepi, dan latar sudah ada SEJAK DIAM — bukan sekadar
 *    `hover:bg-gray-100` di atas ikon telanjang.
 * 2. Sasaran sentuhnya membesar di perangkat sentuh saja (`pointer-coarse:`),
 *    supaya tampilan desktop tidak ikut menggemuk.
 * 3. "Harga khusus" bukan lagi tulisan biru polos di kaki kartu. Ia ikut baris
 *    ikon yang sudah ada, jadi tombol yang jarang dipakai berhenti memungut
 *    satu baris dari SETIAP item keranjang.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi tidak ada tes HTTP yang bisa
 * menangkap kemundurannya — dijaga langsung di sumbernya, mengikuti pola
 * `SidebarAccountMenuTest`.
 */
function cartItemSource(): string
{
    return file_get_contents(resource_path('js/Components/CartItem.vue'));
}

function posSource(): string
{
    return file_get_contents(resource_path('js/Pages/Cashier/POS.vue'));
}

test('aksi baris punya kotak sejak diam, bukan hanya saat hover', function () {
    $source = cartItemSource();

    // Kotak netral yang dipakai semua aksi yang belum menyala.
    expect($source)->toContain("const ACTION_IDLE = 'border-gray-200 bg-gray-50 text-gray-500'")
        ->and($source)->toContain('rounded-lg border')
        // Umpan balik tekan — satu-satunya yang benar-benar terasa di layar sentuh.
        ->and($source)->toContain('active:scale-90');

    // Tidak ada lagi ikon telanjang yang bentuknya baru muncul saat hover.
    expect($source)->not->toContain("'w-8 h-8 flex items-center justify-center rounded-lg transition'")
        ->and($source)->not->toContain('text-gray-400 hover:bg-gray-100 hover:text-primary transition');
});

test('sasaran sentuh membesar hanya di perangkat sentuh', function () {
    $source = cartItemSource();

    // 36px dengan kursor, 44px dengan jari.
    expect($source)->toContain('h-9 w-9 pointer-coarse:h-11 pointer-coarse:w-11')
        // Tombol +/- ikut, karena berdiri sebaris dan justru paling sering ditekan.
        ->and($source)->toContain('h-8 w-8 pointer-coarse:h-10 pointer-coarse:w-10');
});

test('harga khusus ikut baris ikon, bukan baris sendiri di tiap kartu', function () {
    $cartItem = cartItemSource();
    $pos = posSource();

    // Tombolnya kini milik CartItem, sebaris dengan catatan/ubah/hapus.
    expect($cartItem)->toContain('canSetSpecialPrice')
        ->and($cartItem)->toContain(':label="specialPriceLabel"')
        ->and($cartItem)->toContain("emit('specialPrice', props.index)")
        ->and($cartItem)->toContain("emit('clearSpecialPrice', props.index)");

    // Kaki kartu yang dulu selalu ada sudah hilang dari POS.
    expect($pos)->not->toContain('class="text-[11px] font-medium text-primary hover:text-primary/80"')
        ->and($pos)->toContain(':can-set-special-price="isOwner"')
        ->and($pos)->toContain('@special-price="openOverride"')
        ->and($pos)->toContain('@clear-special-price="clearOverride"');
});

test('alasan harga khusus hanya memakan baris saat harganya memang berlaku', function () {
    $source = cartItemSource();

    expect($source)->toContain('v-if="item.override_unit_price"')
        ->and($source)->toContain('Harga khusus — “{{ item.discount_reason }}”')
        // Membatalkan tetap satu tombol betulan, bukan tulisan kelabu.
        ->and($source)->toContain('@click="clearSpecialPrice"');
});

test('kasir tidak diberi tombol harga khusus sama sekali', function () {
    $cartItem = cartItemSource();
    $pos = posSource();

    // Prop-nya default mati, dan satu-satunya yang menyalakannya adalah owner.
    expect($cartItem)->toContain('canSetSpecialPrice: { type: Boolean, default: false }')
        ->and($cartItem)->toContain('v-if="canSetSpecialPrice"')
        ->and($pos)->toContain(':can-set-special-price="isOwner"');
});
