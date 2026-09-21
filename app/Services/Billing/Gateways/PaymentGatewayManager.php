<?php

namespace App\Services\Billing\Gateways;

use InvalidArgumentException;
use RuntimeException;

/**
 * Memilih penyedia pembayaran, dan menutup pintu yang tidak boleh terbuka.
 *
 * Dua tugas, dan yang kedua justru alasan kelas ini ada. Driver tiruan bisa
 * melunasi tagihan tanpa uang berpindah — persis lubang yang `provisional_blocked`
 * dibangun untuk menutup. Sejak `[BL-061]` mencabut tombol peragaan satu klik,
 * gerbang di kelas inilah SATU-SATUNYA yang menjaganya. Karena itu ia tidak sekadar
 * "sebaiknya tidak dipakai di produksi": ia GAGAL DI-RESOLVE di sana, keras,
 * sebelum satu baris pun jalan.
 *
 * Diam-diam jatuh ke jalur manual akan jauh lebih buruk daripada exception:
 * halaman pembayaran tetap terbuka, tombolnya tetap ada, dan tidak ada yang
 * tahu bahwa yang barusan "lunas" tak pernah dibayar.
 */
class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const DRIVERS = [
        'fake' => FakeGateway::class,
    ];

    /**
     * Driver yang melunasi tanpa uang sungguhan berpindah.
     *
     * @var list<string>
     */
    private const SIMULATED = ['fake'];

    public function default(): PaymentGateway
    {
        return $this->driver((string) config('subscription.payment.driver'));
    }

    public function driver(string $key): PaymentGateway
    {
        if (! isset(self::DRIVERS[$key])) {
            throw new InvalidArgumentException("Payment gateway [{$key}] tidak dikenal.");
        }

        if ($this->isSimulated($key) && app()->environment('production')) {
            throw new RuntimeException(
                "Payment gateway [{$key}] adalah gateway tiruan dan tidak boleh hidup di produksi. ".
                'Setel PAYMENT_DRIVER ke penyedia sungguhan.'
            );
        }

        return app(self::DRIVERS[$key]);
    }

    /**
     * Bisakah driver ini dipakai di lingkungan yang sedang berjalan?
     *
     * Dipakai webhook untuk menjawab 404 alih-alih meledak: alamat webhook
     * driver tiruan sebaiknya tidak terlihat pernah ada di produksi.
     */
    public function has(string $key): bool
    {
        if (! isset(self::DRIVERS[$key])) {
            return false;
        }

        return ! ($this->isSimulated($key) && app()->environment('production'));
    }

    public function isSimulated(string $key): bool
    {
        return in_array($key, self::SIMULATED, true);
    }
}
