<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jejak Audit Panel Platform
    |--------------------------------------------------------------------------
    |
    | GARIS ANTARA RUTIN DAN SENSITIF (keputusan BL-009)
    |
    | `routine`  — akses BACA yang wajar berulang: membuka daftar, menyegarkan
    |              halaman, pindah paginasi. Tetap dicatat (pertanyaan "siapa
    |              melihat daftar klien saya minggu lalu?" harus bisa dijawab),
    |              tapi dideduplikasi dalam jendela waktu di bawah supaya satu
    |              sesi kerja tidak menghasilkan puluhan baris identik.
    |
    | `sensitive` — semua yang MENGUBAH keadaan (buat/ubah/hapus akun, kelak
    |              mengubah tarif dan memverifikasi pembayaran), semua yang
    |              membuka DATA BISNIS klien (kelak omset jalur subsidi), dan
    |              kejadian KEAMANAN (login gagal). Selalu dicatat, tanpa
    |              deduplikasi, dan disimpan jauh lebih lama.
    |
    | Kalau ragu menempatkan kejadian baru: tanyakan "kalau klien menuntut
    | pertanggungjawaban, apakah baris ini yang akan saya tunjukkan?" Kalau ya,
    | ia sensitif.
    |
    */

    /**
     * Jendela deduplikasi kejadian rutin, dalam menit.
     *
     * Kejadian rutin yang sama (aktor + aksi + subjek) dalam rentang ini hanya
     * menghasilkan satu baris. 15 menit kira-kira setara "satu sesi menengok".
     */
    'routine_dedupe_minutes' => 15,

    /**
     * Retensi per derajat, dalam hari.
     *
     * Rutin dibuang lebih cepat karena nilainya cepat luruh — yang penting
     * "pernah dibuka sekitar tanggal itu", bukan menyimpannya bertahun-tahun.
     * Sensitif disimpan lama justru karena itulah yang dipakai membuktikan.
     */
    'retention_days' => [
        'routine' => 90,
        'sensitive' => 730,
    ],

];
