<?php

/*
|--------------------------------------------------------------------------
| Penjaga dari penjaga — `[BL-110]`
|--------------------------------------------------------------------------
|
| `tests:check-helper-names` menjaga sesuatu yang TIDAK BISA dijaga oleh sebuah
| tes: bentrokan nama pembantu uji mematikan pelari uji pada tahap pemuatan,
| sebelum satu assertion pun berjalan. Dicoba dengan menanam `posSource()` kedua
| — yang keluar cuma `Fatal error: Cannot redeclare posSource()` dan status 255,
| tanpa satu tes pun sempat jalan. Karena itu penjaganya sebuah perintah Artisan
| yang berjalan SEBELUM `php artisan test` di `composer run check:boot`.
|
| Yang diuji di sini bukan bentrokannya, melainkan PERINTAHNYA: bahwa ia benar
| menyala saat ada bentrokan, benar diam saat tidak ada, dan tidak salah lapor
| pada metode di dalam kelas. Sebuah penjaga yang tidak pernah bisa gagal persis
| yang ditolak `[BL-072]` butir (d), jadi kasus keduanya menanam cacatnya betulan.
|
| Nama pembantu di berkas ini sendiri berawalan `helperGuard` — berkas yang
| menguji penjaga bentrokan nama sebaiknya tidak ikut menyebabkannya.
|
*/

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Jalankan penjaganya dan kembalikan status beserta keluarannya.
 *
 * Lewat `Artisan::call()`, bukan `$this->artisan()->expectsOutputToContain()`:
 * yang kedua MENGHABISKAN baris yang sudah cocok, jadi dua harapan terhadap
 * satu baris yang sama membuat harapan kedua gagal walau barisnya benar.
 * Ditemukan saat menulis tes ini.
 *
 * @return array{status: int, output: string}
 */
function helperGuardRun(?string $path = null): array
{
    $status = Artisan::call('tests:check-helper-names', $path === null ? [] : ['path' => $path]);

    return ['status' => $status, 'output' => Artisan::output()];
}

/**
 * Direktori contoh yang berumur satu tes.
 */
function helperGuardFixtureDir(): string
{
    $dir = sys_get_temp_dir().'/helper-guard-'.uniqid();

    mkdir($dir.'/nested', recursive: true);

    return $dir;
}

/**
 * Tulis satu berkas uji palsu berisi `$body` apa adanya.
 */
function helperGuardWrite(string $dir, string $name, string $body): void
{
    file_put_contents($dir.'/'.$name, "<?php\n\n".$body."\n");
}

function helperGuardCleanup(string $dir): void
{
    foreach (glob($dir.'/{,nested/}*', GLOB_BRACE) as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }

    @rmdir($dir.'/nested');
    @rmdir($dir);
}

test('suite yang sebenarnya bersih dari nama ganda', function () {
    // Bukan sekadar memeriksa perintahnya jalan: ini yang menahan bentrokan
    // berikutnya masuk ke repositori. Kalau tes ini merah, `check:boot` sudah
    // mati juga — dan pesannya ada di keluaran perintahnya, bukan di sini.
    expect(helperGuardRun()['status'])->toBe(Command::SUCCESS);
});

test('bentrokan yang ditanam benar-benar tertangkap, lintas subdirektori', function () {
    // Cacatnya ditirukan persis seperti yang terjadi: dua berkas, nama sama,
    // badan sama, di kedalaman direktori yang berbeda — karena bentrokan aslinya
    // pun antara `tests/Feature/` dan `tests/Feature/Cashier/`.
    $dir = helperGuardFixtureDir();

    helperGuardWrite($dir, 'AlphaTest.php', "function posSource(): string\n{\n    return 'a';\n}");
    helperGuardWrite($dir, 'nested/BetaTest.php', "function posSource(): string\n{\n    return 'b';\n}");

    ['status' => $status, 'output' => $output] = helperGuardRun($dir);

    expect($status)->toBe(Command::FAILURE)
        ->and($output)->toContain('posSource()')
        // Menyebut KEDUA tempatnya, karena yang dilihat orang pertama kali
        // adalah fatal error yang cuma menyebut satu.
        ->and($output)->toContain('AlphaTest.php:3')
        ->and($output)->toContain('BetaTest.php:3');

    helperGuardCleanup($dir);
});

test('nama yang hanya muncul sekali tidak dilaporkan', function () {
    $dir = helperGuardFixtureDir();

    helperGuardWrite($dir, 'AlphaTest.php', "function cartItemSource(): string\n{\n    return 'a';\n}");
    helperGuardWrite($dir, 'nested/BetaTest.php', "function coldStartPosSource(): string\n{\n    return 'b';\n}");

    expect(helperGuardRun($dir)['status'])->toBe(Command::SUCCESS);

    helperGuardCleanup($dir);
});

test('metode di dalam kelas tidak pernah dilaporkan walau namanya sama', function () {
    // Ini pembeda yang menentukan apakah penjaga ini akan dipercaya atau
    // dimatikan orang. Metode selalu menjorok dan TIDAK pernah bertabrakan —
    // melaporkannya berarti melapor palsu di hampir setiap berkas `tests/Unit`.
    // Pemisahnya sama dengan yang dipakai `CommitBootabilityTest`: polanya
    // menempel di awal baris.
    $dir = helperGuardFixtureDir();

    $class = "class AlphaHelper\n{\n    public function posSource(): string\n    {\n        return 'a';\n    }\n}";
    $other = "class BetaHelper\n{\n    public function posSource(): string\n    {\n        return 'b';\n    }\n}";

    helperGuardWrite($dir, 'AlphaTest.php', $class);
    helperGuardWrite($dir, 'nested/BetaTest.php', $other);

    expect(helperGuardRun($dir)['status'])->toBe(Command::SUCCESS);

    helperGuardCleanup($dir);
});

test('deklarasi bersyarat di dalam function_exists tidak dianggap bentrokan', function () {
    // Bentuk `if (! function_exists('x')) { function x() {} }` memang dibuat
    // supaya aman dideklarasikan dua kali. Ia menjorok, jadi ikut terlewat —
    // dan itu disengaja, bukan kebetulan.
    $dir = helperGuardFixtureDir();

    $guarded = "if (! function_exists('posSource')) {\n    function posSource(): string\n    {\n        return 'a';\n    }\n}";

    helperGuardWrite($dir, 'AlphaTest.php', $guarded);
    helperGuardWrite($dir, 'nested/BetaTest.php', $guarded);

    expect(helperGuardRun($dir)['status'])->toBe(Command::SUCCESS);

    helperGuardCleanup($dir);
});

test('direktori yang tidak ada dilaporkan, bukan didiamkan', function () {
    // Penjaga yang lulus karena tidak menemukan apa-apa adalah penjaga yang
    // tidak ada. Salah ketik jalur harus merah, bukan hijau.
    expect(helperGuardRun(sys_get_temp_dir().'/tidak-ada-'.uniqid())['status'])->toBe(Command::FAILURE);
});
