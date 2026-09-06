<?php

/*
|--------------------------------------------------------------------------
| Penjaga "Halaman yang Dirender Harus Ada" — `[BL-098]`
|--------------------------------------------------------------------------
|
| `Platform\InvoiceController::index()` bertahan berminggu-minggu sebagai 30
| baris yang terbaca seperti fitur hidup — lengkap dengan query, paginasi, dan
| pencatatan audit — padahal komponen Vue yang direndernya sudah terhapus dan
| tidak ada satu pun rute yang bisa memanggilnya. Ia tidak pernah 500 justru
| karena tidak ada yang bisa menyentuhnya.
|
| Yang membuatnya bertahan bukan kesulitan teknis, melainkan bahwa satu-satunya
| cara menemukannya adalah membandingkan daftar `Inertia::render()` di seluruh
| controller dengan daftar berkas yang benar-benar ada di disk — pekerjaan yang
| tidak dilakukan siapa pun dalam sehari-hari. Test ini yang melakukannya.
|
| Yang dijaga SATU hal saja: setiap nama halaman yang dirender punya berkasnya.
| "Apakah methodnya bisa dicapai sebuah rute" adalah pertanyaan lain, dan tidak
| dijawab di sini — tapi method mati yang merender halaman mati akan tertangkap
| di sini begitu halamannya ikut terhapus, dan itulah bentuk yang sudah terjadi.
|
*/

/**
 * Setiap halaman Inertia yang dirender kode sendiri, beserta tempat merendernya.
 *
 * Dua bentuk pemanggilan dipakai berdampingan di basis kode ini —
 * `Inertia::render('X')` di sebagian besar controller dan helper `inertia('X')`
 * di kelompok Billing — jadi keduanya disisir. Hanya argumen berupa literal
 * string yang diambil; nama halaman yang dirakit dari variabel tidak bisa
 * diperiksa tanpa menjalankan kodenya, dan hari ini tidak ada satu pun.
 *
 * @return array<int, array{page: string, file: string, line: int}>
 */
function renderedInertiaPages(): array
{
    $found = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        foreach (file($file->getPathname()) as $index => $line) {
            if (preg_match("/(?:Inertia::render|\binertia)\(\s*'([^']+)'/", $line, $matches) !== 1) {
                continue;
            }

            $found[] = [
                'page' => $matches[1],
                'file' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()),
                'line' => $index + 1,
            ];
        }
    }

    return $found;
}

test('penjaganya menemukan halaman untuk diperiksa', function () {
    // Penjaga yang polanya tidak lagi cocok akan lulus tanpa memeriksa apa pun.
    // Angkanya sengaja longgar — yang dijaga di sini "masih menemukan sesuatu",
    // bukan jumlah halaman yang kebetulan ada hari ini.
    expect(renderedInertiaPages())->not->toBeEmpty(
        'Tidak ada satu pun pemanggilan Inertia::render() atau inertia() yang '.
        'terbaca di app/. Bila cara merender halaman berubah, penjaga ini harus '.
        'ikut diperbarui — penjaga yang tidak menemukan apa pun tidak menjaga apa pun.',
    );
});

test('setiap halaman yang dirender punya komponen Vue-nya di disk', function () {
    $hilang = [];

    foreach (renderedInertiaPages() as $render) {
        $komponen = resource_path('js/Pages/'.$render['page'].'.vue');

        if (! file_exists($komponen)) {
            $hilang[] = "{$render['file']}:{$render['line']} merender '{$render['page']}', ".
                'tapi resources/js/Pages/'.$render['page'].'.vue tidak ada.';
        }
    }

    expect($hilang)->toBeEmpty(implode("\n", array_merge(
        ['Ada kode yang merender halaman Vue yang tidak ada di disk:', ''],
        $hilang,
        [
            '',
            'Bila halamannya memang sudah dihapus, kode yang merendernya ikut',
            'dihapus — bukan dibiarkan karena "toh tidak ada rutenya". Bila',
            'halamannya yang belum dibuat, buatlah sebelum merendernya.',
        ],
    )));
});
