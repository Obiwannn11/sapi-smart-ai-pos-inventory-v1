<?php

/*
|--------------------------------------------------------------------------
| Penjaga "Satu Commit Harus Bisa Boot Sendiri" — `[BL-072]` butir (d)
|--------------------------------------------------------------------------
|
| `[BL-072]` mencatat enam commit berturut-turut yang memanggil kelas yang
| berkasnya belum ada, dan menutup entrinya dengan satu aturan: kelas dan
| pemakainya masuk di commit yang sama, atau kelasnya lebih dulu.
|
| Uji cepat yang disarankan entri itu — `php artisan route:list` — DICOBA dan
| TERNYATA TIDAK MENANGKAPNYA. Alasannya ada di PHP, bukan di Laravel: sebuah
| `use App\Models\PaymentAttempt;` hanyalah alias di waktu kompilasi. Ia tidak
| pernah memicu autoloader sampai kelasnya benar-benar DIPAKAI. Diverifikasi
| dengan menirukan cacat aslinya persis — impor digantung di
| `HandleInertiaRequests`, lalu `route:list` tetap keluar dengan status 0.
|
| Karena itu penjaganya harus membaca impornya, bukan menjalankan aplikasinya.
| Yang di bawah ini menyisir setiap impor tingkat atas di kode yang kita tulis
| sendiri dan menuntut sasarannya benar-benar bisa dimuat — persis bentuk cacat
| yang membuat rentang `2ffd393`..`329f592` tidak bisa dirender sama sekali.
|
| Jalankan lewat `composer run check:boot` sebelum commit.
|
*/

/**
 * Direktori kode milik sendiri yang ikut disisir.
 *
 * `vendor/` sengaja di luar: isinya bukan yang kita commit, dan impor
 * bersyaratnya (paket opsional yang memang boleh tidak terpasang) akan
 * melaporkan kegagalan yang bukan kegagalan.
 *
 * @return array<int, string>
 */
function scannedSourceDirectories(): array
{
    return ['app', 'database', 'routes', 'config'];
}

/**
 * Impor tingkat atas dari sebuah berkas PHP, beserta nomor barisnya.
 *
 * Hanya `use` yang menempel di awal baris yang diambil. Itu memisahkan impor
 * namespace dari pemakaian trait di dalam badan kelas, yang selalu menjorok
 * dan bukan urusan penjaga ini.
 *
 * @return array<int, array{symbol: string, line: int}>
 */
function topLevelImports(string $path): array
{
    $imports = [];

    foreach (file($path) as $index => $line) {
        // `use function` dan `use const` mengimpor simbol yang bukan kelas,
        // jadi `class_exists()` bukan alat yang tepat untuk memeriksanya.
        if (preg_match('/^use\s+(?!function\s|const\s)([A-Za-z0-9_\\\\]+)(?:\s+as\s+[A-Za-z0-9_]+)?;/', $line, $matches) !== 1) {
            continue;
        }

        $imports[] = ['symbol' => $matches[1], 'line' => $index + 1];
    }

    return $imports;
}

test('tidak ada impor yang menggantung ke kelas yang belum ada', function () {
    $dangling = [];

    foreach (scannedSourceDirectories() as $directory) {
        $absolute = base_path($directory);

        if (! is_dir($absolute)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach (topLevelImports($file->getPathname()) as $import) {
                $symbol = $import['symbol'];

                $resolvable = class_exists($symbol)
                    || interface_exists($symbol)
                    || trait_exists($symbol)
                    || enum_exists($symbol);

                if ($resolvable) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

                $dangling[] = $relative.':'.$import['line'].' → '.$symbol;
            }
        }
    }

    expect($dangling)->toBe([], implode("\n", [
        'Impor menggantung — kelasnya tidak bisa dimuat:',
        ...$dangling,
        '',
        'Inilah bentuk persis `[BL-072]`: pemakai sebuah kelas ikut ter-commit',
        'lebih dulu daripada kelasnya. Commit yang seperti ini tetap hijau di',
        'sebagian tes, tapi TIDAK BISA BOOT — dan ia meracuni `git bisect` serta',
        '`git revert` bagi siapa pun yang menyusuri riwayat belakangan.',
        'Masukkan kelasnya di commit yang sama, atau dahulukan kelasnya.',
    ]));
});
