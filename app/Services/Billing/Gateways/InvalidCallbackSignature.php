<?php

namespace App\Services\Billing\Gateways;

use RuntimeException;

/**
 * Notifikasi yang tidak terbukti datang dari penyedianya.
 *
 * Dilempar, bukan dikembalikan sebagai nilai, supaya tidak ada jalan untuk
 * "lupa memeriksa hasilnya": pemanggil yang mengabaikan kegagalan verifikasi
 * akan melunasi tagihan atas perintah siapa saja yang menemukan alamat
 * webhook-nya.
 */
class InvalidCallbackSignature extends RuntimeException {}
