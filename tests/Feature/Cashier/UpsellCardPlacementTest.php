<?php

/**
 * Kartu saran jual berdiri di dasar kolom katalog, dan footer keranjang rapi.
 *
 * Di layar 1366×768 kolom keranjang cuma ±711 px. Selama kartu saran tinggal
 * di sana sebagai panel sendiri (±240 px) di atas footer yang ±275 px, daftar
 * pesanan tersisa ±140 px — satu baris kopi bermodifier ±134 px. Kasir melihat
 * SATU baris pesanan tepat saat ia sedang menawar ke pelanggan.
 *
 * Yang dijaga di sini adalah keputusan tata letaknya, bukan pikselnya: kartu di
 * kolom katalog, tanpa pembatas geser di kolom keranjang, kartu yang melebar
 * menurut wadahnya, dan footer yang tidak lagi mengulang penjelasan. Keempatnya
 * mudah dikembalikan tanpa sengaja oleh siapa pun yang merapikan POS.vue.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi dijaga langsung di sumbernya,
 * mengikuti pola `CartLineActionAffordanceTest`.
 */
function upsellPlacementPosSource(): string
{
    return file_get_contents(resource_path('js/Pages/Cashier/POS.vue'));
}

function upsellPlacementStripSource(): string
{
    return file_get_contents(resource_path('js/Components/UpsellStrip.vue'));
}

test('kartu saran dirender sekali, di kolom katalog, sebelum kolom keranjang', function () {
    $source = upsellPlacementPosSource();

    $strip = strpos($source, '<UpsellStrip');
    $cartPanel = strpos($source, '<!-- RIGHT: Cart Panel -->');

    expect($strip)->not->toBeFalse()
        ->and($cartPanel)->not->toBeFalse()
        ->and($strip)->toBeLessThan($cartPanel)
        ->and(substr_count($source, '<UpsellStrip'))->toBe(1);
});

test('pembatas geser tinggi saran di kolom keranjang sudah tidak ada', function () {
    $source = upsellPlacementPosSource();

    // Pembatas itu hanya menyerahkan pilihan "keranjang atau saran" ke kasir,
    // di kolom yang terlalu pendek untuk keduanya.
    expect($source)->not->toContain('startResizeUpsell')
        ->and($source)->not->toContain('cashier.upsellHeight')
        ->and($source)->not->toContain('Atur tinggi saran untuk pelanggan');
});

test('kartu melebar menurut lebar wadahnya, bukan lebar layar', function () {
    $source = upsellPlacementStripSource();

    // Layar 768 px bisa punya kolom katalog 378 px, layar 1366 px kolom 976 px;
    // breakpoint layar salah menebak keduanya.
    expect($source)->toContain('class="@container')
        ->and($source)->toContain('@2xl:grid');
});

test('tombol bayar yang terkunci tetap menjelaskan dirinya tanpa kotak peringatan', function () {
    $source = upsellPlacementPosSource();

    // Kotak kuning yang mengulang "Wajib dijawab" dibuang...
    expect($source)->not->toContain('rounded-lg border border-amber-200 bg-amber-50 px-3 py-2')
        // ...tapi penjelasannya tidak ([BL-025]): label pendek di tombolnya,
        // kalimat utuh di `title` dan untuk pembaca layar.
        ->and($source)->toContain("{{ processing ? 'Memproses...' : (payButtonHint || 'Bayar') }}")
        ->and($source)->toContain(':title="checkoutBlockedReason || undefined"')
        ->and($source)->toContain('<p v-if="payButtonHint" class="sr-only" role="status">{{ checkoutBlockedReason }}</p>');
});

test('baris keranjang berhenti menyala begitu tidak ada saran yang menunggu', function () {
    $source = upsellPlacementPosSource();

    // Ditemukan saat diuji sebagai kasir: menolak semua saran mencabut kartunya
    // dari DOM sebelum ia sempat mengirim `source` kosong, dan baris asalnya
    // tetap menyala tanpa kartu apa pun di layar.
    expect($source)->toContain('if (activeUpsellTrigger.value === null || upsellSuggestions.value.length === 0) return -1;');
});

test('keputusan diikat ke saran yang tergambar, bukan ke giliran saat diketuk', function () {
    $source = upsellPlacementStripSource();

    // Selama animasi keluar (`mode="out-in"`) kartu lama masih terlihat
    // sementara `current` sudah menunjuk saran berikutnya. Tombol yang membaca
    // `current` saat diketuk memutuskan saran yang belum pernah dilihat kasir.
    expect($source)->toContain(':data-suggestion-key="current.key"')
        ->and($source)->toContain('@click="decide($event, \'accept\')"')
        ->and($source)->toContain('@click="decide($event, \'reject\')"')
        ->and($source)->not->toContain("emit('accept', current)")
        ->and($source)->not->toContain("emit('reject', current)");
});

test('catatan batas diskon kasir tetap tersedia tapi tertutup sampai diminta', function () {
    $source = upsellPlacementPosSource();

    expect($source)->toContain('const showDiscountNote = ref(false);')
        ->and($source)->toContain('v-if="showDiscountNote && !isOwner && cart.length > 0"')
        ->and($source)->toContain('Harga di bawah batas untung hanya bisa ditetapkan pemilik.');
});
