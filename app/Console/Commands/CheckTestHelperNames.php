<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Penjaga "nama pembantu uji harus unik" — `[BL-110]`.
 *
 * Fungsi yang dideklarasikan di puncak berkas Pest masuk ke ruang nama GLOBAL,
 * bukan ke berkasnya. Dua berkas uji yang memilih nama sama menabrakkan PHP ke
 * `Cannot redeclare ...` begitu keduanya dimuat — dan `php artisan test
 * --filter=...` SELALU memuat seluruh berkas uji untuk menyelesaikan filternya.
 *
 * Kenapa penjaganya sebuah perintah Artisan, bukan sebuah tes seperti
 * `CommitBootabilityTest`: DICOBA, dan tes tidak bisa menangkapnya. Bentrokan
 * itu mematikan pelari uji pada tahap PEMUATAN, sebelum satu assertion pun
 * dijalankan — jadi tes penjaga ikut mati bersama yang dijaganya, dan yang
 * muncul cuma fatal error mentah yang menyebut dua berkas tak berhubungan.
 * Artisan tidak memuat `tests/` sama sekali, jadi perintah ini masih hidup
 * justru pada saat satu-satunya ia dibutuhkan.
 *
 * Karena itu urutannya di `composer run check:boot` penting: perintah ini
 * berjalan SEBELUM `php artisan test`.
 */
class CheckTestHelperNames extends Command
{
    protected $signature = 'tests:check-helper-names
                            {path? : Direktori yang disisir (default: tests/)}';

    protected $description = 'Pastikan tidak ada dua berkas uji yang mendeklarasikan fungsi pembantu bernama sama';

    public function handle(): int
    {
        $directory = $this->argument('path') ?? base_path('tests');

        if (! is_dir($directory)) {
            $this->components->error("Direktori tidak ditemukan: {$directory}");

            return self::FAILURE;
        }

        $collisions = array_filter(
            $this->declarationsByName($directory),
            fn (array $sites): bool => count($sites) > 1,
        );

        if ($collisions === []) {
            $this->components->info('Nama pembantu uji unik.');

            return self::SUCCESS;
        }

        ksort($collisions);

        $this->components->error('Nama pembantu uji dideklarasikan lebih dari sekali:');

        foreach ($collisions as $name => $sites) {
            $this->line("  {$name}() → ".implode(', ', $sites));
        }

        $this->newLine();

        foreach ([
            'Fungsi tingkat atas di berkas Pest bersifat global, jadi deklarasi',
            'kedua membuat PHP berhenti dengan `Cannot redeclare` SEBELUM satu',
            'tes pun berjalan — termasuk `check:boot`, penjaga [BL-072]. Beri',
            'salah satunya awalan yang menyebut perkara berkasnya (mis.',
            '`coldStartPosSource()`), mengikuti konvensi pembantu milik berkas',
            'yang sudah dipakai di seluruh tests/.',
        ] as $line) {
            $this->line($line);
        }

        return self::FAILURE;
    }

    /**
     * Setiap deklarasi fungsi tingkat atas di bawah `$directory`, dikelompokkan
     * menurut namanya.
     *
     * @return array<string, array<int, string>> nama → daftar `berkas:baris`
     */
    private function declarationsByName(string $directory): array
    {
        $declarations = [];

        foreach ($this->phpFiles($directory) as $file) {
            foreach ($this->topLevelFunctions($file->getPathname()) as $declaration) {
                $declarations[$declaration['name']][] = $this->relativePath($file->getPathname())
                    .':'.$declaration['line'];
            }
        }

        return $declarations;
    }

    /**
     * Jalur yang enak dibaca manusia: relatif terhadap akar proyek bila berkasnya
     * memang ada di dalamnya, apa adanya bila tidak (mis. direktori contoh milik
     * tes penjaga ini sendiri, yang hidup di direktori sementara).
     */
    private function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', base_path()).'/';

        return str_starts_with($path, $root)
            ? substr($path, strlen($root))
            : $path;
    }

    /**
     * @return \Generator<int, SplFileInfo>
     */
    private function phpFiles(string $directory): \Generator
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                yield $file;
            }
        }
    }

    /**
     * Deklarasi fungsi yang menempel di awal baris, beserta nomor barisnya.
     *
     * Menempel di awal baris itulah yang memisahkan fungsi tingkat atas dari
     * metode di dalam badan kelas, yang selalu menjorok — cara yang sama dipakai
     * `CommitBootabilityTest` untuk memisahkan impor dari pemakaian trait.
     * Deklarasi bersyarat di dalam `function_exists()` ikut terlewat karena ia
     * pun menjorok, dan itu memang yang diinginkan: yang seperti itu tidak
     * pernah bertabrakan.
     *
     * @return array<int, array{name: string, line: int}>
     */
    private function topLevelFunctions(string $path): array
    {
        $functions = [];

        foreach (file($path) as $index => $line) {
            if (preg_match('/^function\s+&?\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $line, $matches) !== 1) {
                continue;
            }

            $functions[] = ['name' => $matches[1], 'line' => $index + 1];
        }

        return $functions;
    }
}
