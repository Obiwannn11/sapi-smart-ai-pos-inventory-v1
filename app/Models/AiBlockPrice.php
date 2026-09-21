<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Harga satu blok kuota AI tambahan — seragam untuk semua paket.
 *
 * Seragam, karena ongkos satu analisis tidak berbeda menurut paket pembelinya;
 * itulah alasan ia tidak pernah jadi kolom di `plans` seperti
 * `extra_seat_price`. Yang berubah sejak 2026-09-16 hanyalah tempat tinggalnya:
 * dari `config/subscription.php` ke satu baris tabel, supaya pemilik SaaS bisa
 * mengubahnya tanpa deploy.
 *
 * **Tidak ada grandfathering di sini,** dan itu keputusan pemilik yang
 * disengaja: blok yang sudah dibeli ikut harga baru pada tagihan berikutnya.
 * Konsekuensinya harus disebut di panel, bukan disembunyikan — di halaman yang
 * sama, tiap angka lain justru dilindungi `effective_from` dan `price_locked`,
 * jadi orang yang menyunting angka ini akan mengira ia berperilaku sama.
 *
 * Tagihan yang SUDAH terbit tetap memegang tarifnya sendiri di
 * `invoices.pricing_context.billing_breakdown.ai_block_price` (`[BL-069]`),
 * sehingga mengubah harga di sini tidak pernah menulis ulang masa lalu.
 *
 * Barisnya paling banyak satu. Selama belum ada, `config/subscription.php`
 * tetap yang berlaku — pola lapis yang sama dengan `ai_quota_policies` dan
 * `config/ai.php`.
 */
class AiBlockPrice extends Model
{
    protected $fillable = ['block_price'];

    protected function casts(): array
    {
        return [
            'block_price' => 'decimal:2',
        ];
    }

    /**
     * Baris yang berlaku, atau `null` bila belum pernah disunting siapa pun.
     */
    public static function record(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    /**
     * Harga yang benar-benar berlaku hari ini.
     *
     * Satu-satunya jalan membaca angka ini. Pemanggil yang membaca config
     * langsung akan berselisih dengan panel pada hari pertama seseorang
     * menyuntingnya — dan yang salah justru layar yang dipakai memutuskan.
     */
    public static function current(): float
    {
        return (float) (static::record()?->block_price ?? config('subscription.ai_quota.block_price', 0));
    }

    /**
     * Setel harga baru, membuat barisnya bila belum ada.
     */
    public static function put(float $price): self
    {
        $record = static::record();

        if ($record === null) {
            return static::create(['block_price' => $price]);
        }

        $record->update(['block_price' => $price]);

        return $record;
    }
}
