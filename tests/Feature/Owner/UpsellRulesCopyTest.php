<?php

/**
 * Halaman Aturan Saran Jual menjawab "kenapa aturan saya tidak tampil" tanpa
 * kosakata mesin.
 *
 * Yang dijaga: satu kata "tampil" (bukan bergantian dengan "muncul"), tanpa
 * "jendela", "perebutan", dan "penjagaan" di kalimat yang dibaca pemilik, dan
 * nama jenis yang sama dengan saklarnya di Setelan.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi dijaga langsung di sumbernya,
 * mengikuti pola `UpsellReportCopyTest`.
 */
function upsellRulesPageSource(): string
{
    return file_get_contents(resource_path('js/Pages/Owner/UpsellRules/Index.vue'));
}

function upsellRulesResolverSource(): string
{
    return file_get_contents(app_path('Services/Upsell/RuleOutcomeResolver.php'));
}

test('halaman aturan saran jual tidak lagi memakai istilah lama', function (string $phrase) {
    expect(upsellRulesPageSource())->not->toContain($phrase);
})->with([
    "label: 'Muncul di kasir'",
    '}} diam</template>',
    'Tidak muncul sama sekali, dan kenapa',
    'Kapan saran ini muncul',
    '(tanpa pemicu)',
    'Kosong = sampai dimatikan',
    'label="Aktif"',
    "'Selamanya'",
    'Naikkan — ',
    'Turunkan — ',
    '— naikkan urutannya',
    'pakai Matikan — ',
    'jadi ia tidak pernah tampil di daftar atas',
    "'aturan Anda sendiri'",
]);

test('kalimat status aturan tidak memakai kosakata mesin', function (string $phrase) {
    expect(upsellRulesResolverSource())->not->toContain($phrase);
})->with([
    "'Jendelanya ",
    "'Ubah jendela'",
    "'Tidak ikut perebutan'",
    "'Lolos semua penjagaan",
    "'Pemicunya hilang'",
    "'Barangnya hilang'",
    "'Jenis Anda matikan'",
    'jenis saran "pilihan pemilik"',
    "'Stoknya nol, jadi kasir tidak menawarkannya.'",
    "'Muncul di kasir ",
    "' — naikkan urutannya",
]);

test('nama jenis di kalimat status sama dengan judul saklarnya di setelan', function () {
    $settings = file_get_contents(resource_path('js/Pages/Owner/Settings/Operations.vue'));

    expect($settings)->toContain("title: 'Aturan Anda'")
        ->and(upsellRulesResolverSource())->toContain('jenis saran "Aturan Anda"');
});
