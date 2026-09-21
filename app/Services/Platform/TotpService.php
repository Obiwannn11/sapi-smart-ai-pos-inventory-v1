<?php

namespace App\Services\Platform;

use Illuminate\Support\Str;

/**
 * TOTP (RFC 6238) — kode enam angka dari aplikasi authenticator.
 *
 * **Ditulis sendiri, bukan lewat paket, dan itu keputusan sadar.** Menambah
 * dependensi butuh persetujuan lebih dulu di proyek ini, sedangkan seluruh
 * algoritmanya muat dalam satu kelas: HMAC-SHA1 atas nomor langkah waktu,
 * dipotong secara dinamis, diambil enam digit terakhir. Yang tidak ditulis
 * sendiri adalah kriptografinya — `hash_hmac` dan `hash_equals` milik PHP yang
 * mengerjakannya.
 *
 * TIGA HAL YANG PALING MUDAH SALAH DI IMPLEMENTASI TOTP, DAN CARA MASING-MASING
 * DITANGANI DI SINI:
 *
 *   1. TOLERANSI JAM. Jam ponsel dan jam server tidak pernah persis sama.
 *      Tanpa toleransi, sebagian pengguna tidak akan pernah bisa masuk sama
 *      sekali; dengan toleransi terlalu longgar, jendela sebuah kode yang
 *      tercuri melebar tanpa alasan. Satu langkah ke belakang dan satu ke
 *      depan (±30 detik) adalah yang dipakai hampir semua implementasi.
 *
 *   2. PEMBANDINGAN STRING. `===` pada kode membocorkan berapa banyak digit
 *      awal yang benar lewat lama eksekusinya. `hash_equals` tidak.
 *
 *   3. BASE32, BUKAN BASE64. Aplikasi authenticator membaca rahasia dalam
 *      base32 tanpa padding. Ini alasan alfabetnya ditulis eksplisit di bawah
 *      alih-alih memakai fungsi bawaan PHP — tidak ada fungsi bawaan untuk
 *      base32.
 */
class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Panjang rahasia dalam byte; 20 byte = 160 bit, sesuai anjuran RFC 4226. */
    private const SECRET_BYTES = 20;

    private const DIGITS = 6;

    /** Detik per langkah waktu. 30 adalah nilai yang diasumsikan tiap authenticator. */
    private const PERIOD = 30;

    /** Langkah toleransi ke belakang DAN ke depan. Lihat catatan (1) di atas. */
    private const WINDOW = 1;

    public function generateSecret(): string
    {
        return $this->toBase32(random_bytes(self::SECRET_BYTES));
    }

    /**
     * Kode pemulihan sekali pakai.
     *
     * Wajib ada, bukan pelengkap: ponsel hilang tanpa kode pemulihan berarti
     * akun yang memegang data administratif seluruh klien terkunci permanen,
     * dan satu-satunya jalan keluar jadi menyunting basis data langsung.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /**
     * Apakah kode ini sah untuk rahasia tersebut, dalam toleransi jam.
     */
    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $step = (int) floor(time() / self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            // hash_equals, bukan ===: lihat catatan (2) di atas.
            if (hash_equals($this->codeAt($secret, $step + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * URI `otpauth://` yang dibaca aplikasi authenticator.
     *
     * Dipakai sebagai isi kode QR, dan — di panel ini — juga ditampilkan apa
     * adanya bersama rahasianya untuk dimasukkan manual. Semua authenticator
     * arus utama menerima pemasukan manual, dan panel ini hanya dipakai
     * segelintir akun internal.
     */
    public function provisioningUri(string $secret, string $email, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$email).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
    }

    /**
     * Rahasia dipecah per empat huruf — dibaca dan diketik manusia, dan
     * kesalahan mengetik 32 huruf beruntun adalah kegagalan yang tidak punya
     * pesan galat yang berguna.
     */
    public function formatForDisplay(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    /**
     * Kode enam angka pada satu langkah waktu tertentu.
     */
    private function codeAt(string $secret, int $step): string
    {
        $binary = $this->fromBase32($secret);

        if ($binary === '') {
            return '';
        }

        // Nomor langkah sebagai bilangan bulat 64-bit big-endian.
        $hash = hash_hmac('sha1', pack('J', $step), $binary, true);

        // Pemotongan dinamis (RFC 4226 §5.3): empat bit terakhir hash menunjuk
        // offset tempat empat byte kode diambil, dan bit tertinggi dibuang
        // supaya hasilnya tidak pernah negatif.
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function toBase32(string $binary): string
    {
        $bits = '';

        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 5) as $chunk) {
            $output .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $output;
    }

    private function fromBase32(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');

        if ($secret === '') {
            return '';
        }

        $bits = '';

        foreach (str_split($secret) as $character) {
            $index = strpos(self::ALPHABET, $character);

            if ($index === false) {
                return '';
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        // Sisa bit yang tidak genap satu byte dibuang — itu padding base32,
        // bukan data.
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
