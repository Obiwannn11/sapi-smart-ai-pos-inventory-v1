<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Hub dokumentasi publik: panduan penggunaan dan dokumentasi developer.
 *
 * Isinya markdown di `resources/docs/`, dirender di server dengan
 * league/commonmark bawaan Laravel — pola yang sama dengan dokumen persetujuan.
 * Berkas markdown jauh lebih ramah ditulis dan ditinjau daripada Blade untuk
 * teks yang isinya memang prosa.
 *
 * Referensi API Mobile TIDAK ikut ke sini: halaman itu punya komponen sendiri
 * (kartu endpoint, badge method, tombol salin) yang tidak terwakili markdown,
 * dan sudah berdiri baik di `/api-docs`. Hub ini menautkannya.
 */
class DocsController extends Controller
{
    public function index(): View
    {
        return view('public.docs.index', [
            'tracks' => config('docs.tracks'),
        ]);
    }

    public function show(string $track, ?string $page = null): View
    {
        $tracks = config('docs.tracks');

        abort_unless(isset($tracks[$track]), 404);

        $pages = $tracks[$track]['pages'];
        // Tanpa halaman yang diminta, buka yang pertama. Jalur dokumentasi
        // selalu punya urutan baca yang dimaksudkan penulisnya.
        $page ??= array_key_first($pages);

        // Daftar di config yang jadi gerbangnya, BUKAN keberadaan berkas.
        // Kalau berkasnya yang menentukan, `{page}` dari URL berubah jadi jalan
        // menyusuri sistem berkas.
        abort_unless(isset($pages[$page]), 404);

        $path = resource_path("docs/{$track}/{$page}.md");

        abort_unless(is_file($path), 404);

        return view('public.docs.page', [
            'track' => $track,
            'trackMeta' => $tracks[$track],
            'page' => $page,
            'pageMeta' => $pages[$page],
            'html' => Str::markdown(file_get_contents($path)),
            'neighbours' => $this->neighbours($pages, $page),
        ]);
    }

    /**
     * Halaman sebelum dan sesudah, untuk tautan lanjut di kaki halaman.
     *
     * Dokumentasi tanpa "berikutnya" menuntut pembacanya kembali ke daftar isi
     * tiap selesai satu halaman — dan kebanyakan orang berhenti di situ.
     *
     * @param  array<string, array{title: string, summary: string}>  $pages
     * @return array{prev: ?array{slug: string, title: string}, next: ?array{slug: string, title: string}}
     */
    private function neighbours(array $pages, string $current): array
    {
        $slugs = array_keys($pages);
        $index = array_search($current, $slugs, true);

        $at = function (int|false $position) use ($slugs, $pages): ?array {
            if ($position === false || ! isset($slugs[$position])) {
                return null;
            }

            return ['slug' => $slugs[$position], 'title' => $pages[$slugs[$position]]['title']];
        };

        return [
            'prev' => $index > 0 ? $at($index - 1) : null,
            'next' => $at($index === false ? false : $index + 1),
        ];
    }
}
