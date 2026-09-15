<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membuat setiap jawaban rute konektor terbaca oleh AI pengguna (`[BL-102]`).
 *
 * Yang membuka link adalah pengambil halaman AI, bukan peramban atau aplikasi.
 * Tanpa kelas ini gerbang yang menolak memakai bentuk bawaannya: link yang
 * kedaluwarsa atau langganan yang ditangguhkan dijawab dengan pengalihan ke
 * halaman masuk atau halaman tagihan, dan AI membacakan isi halaman itu. Prompt
 * bawaan meminta AI mengatakan "link gagal dibuka" alih-alih menebak, dan itu
 * hanya berguna kalau penolakannya memang terbaca sebagai penolakan.
 *
 * Caranya dua langkah: meminta JSON dari gerbang di dalamnya (seluruh gerbang
 * API di aplikasi ini menjawab JSON bila diminta), lalu mengubah jawaban yang
 * tidak sukses menjadi kalimat teks biasa.
 *
 * Harus berjalan PALING LUAR. Didaftarkan di depan Authenticate pada daftar
 * prioritas middleware (`bootstrap/app.php`), karena Laravel memindahkan
 * Authenticate dan throttle ke depan middleware yang tidak berprioritas.
 */
class ConnectorPlainTextResponses
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        if (! $response->isSuccessful()) {
            $retryAfter = $response->headers->get('Retry-After');

            $response = response($this->explain($response)."\n", $response->getStatusCode(), [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);

            if ($retryAfter !== null) {
                $response->headers->set('Retry-After', $retryAfter);
            }
        }

        // Link berisi kunci akses: jangan disimpan perantara, jangan diindeks,
        // dan jangan bocorkan URL-nya lewat Referer.
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function explain(Response $response): string
    {
        if ($response->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
            return 'Link ini tidak berlaku: sudah dicabut, sudah lewat masa berlakunya, atau tidak tersalin utuh. Buat link baru di SAPI, halaman Setelan, Integrasi & Kredensial.';
        }

        $message = $response instanceof JsonResponse
            ? ($response->getData(true)['message'] ?? null)
            : null;

        return 'Data toko tidak bisa dibuka lewat link ini. '.($message ?? 'Coba lagi nanti.');
    }
}
