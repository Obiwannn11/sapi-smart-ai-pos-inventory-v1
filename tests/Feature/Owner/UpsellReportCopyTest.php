<?php

/**
 * Laporan Saran Jual memakai satu kata per tahap, sama dengan tombol kasir.
 *
 * Sebelumnya tahap "pelanggan mau" disebut empat cara di satu halaman: "Jadi
 * dibeli", "Diterima", "Diambil", dan "Sukses tawar". Tahap tengah disebut
 * "Ditawarkan kasir", padahal `offered` = diterima + ditolak: sistem hanya tahu
 * kasir menekan jawaban.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi dijaga langsung di sumbernya,
 * mengikuti pola `DashboardCopyTest`.
 */
function upsellReportCopySource(): string
{
    return file_get_contents(resource_path('js/Pages/Owner/Reports/Upsell.vue'));
}

test('laporan saran jual tidak lagi memakai istilah lama', function (string $phrase) {
    expect(upsellReportCopySource())->not->toContain($phrase);
})->with([
    'Muncul di layar',
    'Ditawarkan kasir',
    'Jadi dibeli',
    'Sukses tawar',
    '>Diambil<',
    'Permukaan',
    'Otomatis (sistem)',
    'Aturan Anda sendiri',
    'Saran Jual (Upsell)',
    'Modal mati di rak',
    'rentang tanggal di atas',
    'didorong lewat saran',
]);

test('laporan saran jual menamai tahapnya seperti tombol kasir', function () {
    $source = upsellReportCopySource();

    expect($source)->toContain("label: 'Tampil'")
        ->and($source)->toContain("label: 'Dijawab kasir'")
        ->and($source)->toContain("label: 'Diterima'")
        ->and($source)->toContain('Tingkat terima')
        ->and($source)->toContain("title: 'Modal hangus'");
});

test('persentase laporan memakai format Indonesia', function () {
    $source = upsellReportCopySource();

    expect($source)->toContain("toLocaleString('id-ID', { maximumFractionDigits: 1 })")
        ->and($source)->not->toContain('({{ rate(row.accepted, row.shown) }}%)')
        ->and($source)->not->toContain("column.summary.offer_rate + '%'");
});

test('pratinjau aturan menyalin kata kasir, dan modal hangus punya satu nama', function () {
    // Pratinjau di halaman Aturan menunjukkan apa yang dibaca kasir, jadi ia
    // harus sama dengan chip di UpsellStrip.vue, bukan istilah laporan.
    expect(file_get_contents(resource_path('js/Components/UpsellStrip.vue')))->toContain("title: 'Segera jual'")
        ->and(file_get_contents(resource_path('js/Pages/Owner/UpsellRules/Index.vue')))->toContain("pressed_stock: { text: 'Segera jual'")
        ->and(file_get_contents(app_path('Services/Upsell/RuleOutcomeResolver.php')))->toContain("TYPE_PRESSED_STOCK => 'Segera jual'")
        ->and(file_get_contents(resource_path('js/Pages/Owner/Dashboard.vue')))->not->toContain('Modal basi')
        ->and(file_get_contents(app_path('Services/BadgeHelperService.php')))->toContain(" hangus'");
});

test('setelan menamai jenis saran persis seperti tabel laporan menamainya', function () {
    // Docblock `UPSELL_TYPES` menyebut owner sampai ke setelan DARI tabel "Per
    // Jenis Saran". Dua dari empat namanya dulu berganti di perjalanan itu:
    // "Tambah add-on" jadi "Tambahan (add-on)", "Aturan Anda" jadi "Aturan yang
    // Anda tulis sendiri". Baris `detail` di bawah judulnya yang menerangkan,
    // bukan judulnya.
    $settings = file_get_contents(resource_path('js/Pages/Owner/Settings/Operations.vue'));

    preg_match_all("/(attach|pressed_stock|upsize|manual): '([^']+)',/", upsellReportCopySource(), $matches, PREG_SET_ORDER);

    expect($matches)->toHaveCount(4);

    foreach ($matches as [, $key, $label]) {
        expect($settings)->toContain("key: '{$key}',")
            ->and($settings)->toContain("title: '{$label}',");
    }
});
