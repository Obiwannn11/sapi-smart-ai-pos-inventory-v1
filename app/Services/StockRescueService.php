<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\UpsellEvent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dua angka yang menutup rantai barang tertekan ([BL-105]).
 *
 * Rantainya sudah utuh sejak `[BL-017]` dan `[BL-018]`: sinyal stok melahirkan
 * potongan yang mendalam ke arah hari kedaluwarsa, potongan itu sampai ke
 * mulut kasir lewat `PressedStockStrategy`, dan nasib tiap saran tercatat di
 * `upsell_events`. Yang tidak pernah ada adalah **angkanya** — `pressed_stock`
 * berhenti sebagai satu baris berlabel "Barang tertekan" di tabel rekap per
 * jenis, dan tidak ada satu pun tempat di `app/` yang menilai barang basi
 * dalam rupiah.
 *
 * Kelas ini menjawab keduanya, dan sengaja menaruhnya berdampingan supaya
 * definisinya tidak berselisih di dua layar.
 *
 * **Kedua angkanya TIDAK berperiode sama, dan itu bukan kelalaian.** Yang satu
 * rentang tanggal, yang satu potret hari ini — alasannya ada di
 * {@see self::spoiled()}. Permukaan yang memakainya wajib menamai bedanya;
 * menyandingkan keduanya dengan label yang tidak membedakannya adalah angka
 * yang berbohong.
 */
class StockRescueService
{
    /**
     * Saran yang boleh ikut dihitung: yang tidak menempel pada transaksi batal.
     *
     * Transaksi yang di-void bukan penjualan, jadi upsell di dalamnya bukan
     * upsell yang berhasil ([BL-092]). Sebelum aturan itu ada, kasir yang
     * membatalkan lalu memasukkan ulang satu transaksi membuat saran yang sama
     * terhitung DUA KALI — sekali pada transaksi yang sudah dibatalkan, sekali
     * lagi pada penggantinya.
     *
     * `whereDoesntHave` sengaja, bukan join: `transaction_id` boleh NULL karena
     * transaksi yang benar-benar dihapus melepasnya, dan migrasinya memilih itu
     * justru supaya menghapus transaksi tidak diam-diam memperbaiki angka
     * konversi.
     *
     * Dipakai bersama Laporan Saran Jual supaya angka utama di kepala halaman
     * dan tabel di bawahnya tidak pernah menghitung himpunan yang berbeda.
     *
     * Tenantnya disebut eksplisit, tidak menumpang `TenantScope`: scope itu
     * mati begitu tidak ada yang login (lihat `TenantScope::apply()`), jadi
     * pemanggil tanpa sesi — tugas terjadwal, perintah artisan — akan diam-diam
     * menghitung SELURUH tenant. Di dalam permintaan owner, saringan ini cuma
     * mengulang apa yang sudah dilakukan scope, dan itu murah.
     *
     * @return Builder<UpsellEvent>
     */
    public function countableEvents(Tenant $tenant, string $from, string $to): Builder
    {
        return UpsellEvent::query()
            ->where('upsell_events.tenant_id', $tenant->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->whereDoesntHave('transaction', fn ($query) => $query->where('status', Transaction::STATUS_VOIDED));
    }

    /**
     * Omzet yang lahir dari saran barang tertekan yang benar-benar diambil.
     *
     * Ini **omzet, bukan modal yang diselamatkan**. `extra_amount` adalah harga
     * efektif yang dibayar pelanggan (sudah termasuk potongan `near_expiry`
     * bila ada), dicatat `UpsellEventRecorder` hanya untuk saran berstatus
     * `accepted`. Menyebutnya "kerugian yang dicegah" akan melebih-lebihkan:
     * sebagian dari angka ini adalah barang yang mungkin laku juga tanpa
     * disarankan. Yang dijanjikan angka ini cuma satu hal, dan itu benar —
     * sekian rupiah masuk lewat saran yang muncul karena barangnya tertekan.
     *
     * @return array{amount: float, accepted: int, shown: int}
     */
    public function rescued(Tenant $tenant, string $from, string $to): array
    {
        $row = $this->countableEvents($tenant, $from, $to)
            ->where('type', UpsellEvent::TYPE_PRESSED_STOCK)
            ->selectRaw('COUNT(*) as shown')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as accepted', [UpsellEvent::STATUS_ACCEPTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN extra_amount ELSE 0 END) as amount', [UpsellEvent::STATUS_ACCEPTED])
            ->first();

        return [
            'amount' => (float) ($row->amount ?? 0),
            'accepted' => (int) ($row->accepted ?? 0),
            'shown' => (int) ($row->shown ?? 0),
        ];
    }

    /**
     * Modal yang sudah tidak bisa kembali: barang kedaluwarsa yang masih di rak.
     *
     * Dinilai pada `cost_price`, bukan `price`. Barang yang tidak pernah terjual
     * tidak pernah menghasilkan margin, jadi yang hilang adalah uang yang sudah
     * dikeluarkan untuk membelinya — menilainya pada harga jual akan melaporkan
     * kerugian yang lebih besar daripada yang benar-benar terjadi.
     *
     * **Ini POTRET HARI INI, bukan angka periode, dan tidak bisa jadi angka
     * periode dengan data yang ada hari ini.** `stock` adalah nilai sekarang,
     * bukan sejarah: begitu owner membuang barangnya dan menyesuaikan stok jadi
     * nol, kerugian itu lenyap tanpa jejak. `stock_movements` tidak menolong —
     * kelima jenisnya (`sale`, `restock`, `adjustment`, `void`, `edit`) tidak
     * ada yang berarti "dibuang", jadi pembuangan tersamar sebagai `adjustment`
     * bersama koreksi hitung dan barang pecah.
     *
     * Angka periodenya menuntut pencatat harian yang menstempel satu baris saat
     * sebuah varian melewati `expiry_date` dengan sisa stok — dan pencatat itu
     * tidak bisa ditambal mundur, persis alasan `upsell_events` dulu lahir
     * sebelum permukaannya. Tempatnya `[BL-105]`, bukan di sini.
     *
     * @return array{amount: float, variants: int, units: int}
     */
    public function spoiled(Tenant $tenant): array
    {
        $row = $this->expiredOnShelf($tenant)
            ->selectRaw('COUNT(*) as variants')
            ->selectRaw('COALESCE(SUM(stock), 0) as units')
            ->selectRaw('COALESCE(SUM(stock * cost_price), 0) as amount')
            ->first();

        return [
            'amount' => (float) ($row->amount ?? 0),
            'variants' => (int) ($row->variants ?? 0),
            'units' => (int) ($row->units ?? 0),
        ];
    }

    /**
     * Varian yang sudah lewat tanggal kedaluwarsa dan stoknya belum habis.
     *
     * Satu definisi untuk dua pembaca: Badge "Sudah Expired" di dashboard yang
     * mendaftar barangnya, dan {@see self::spoiled()} yang menjumlahkan
     * rupiahnya. Dua definisi yang berselisih hanya akan terlihat sebagai
     * daftar berisi lima baris dengan nilai total milik enam barang, jauh
     * setelah penyebabnya dilupakan.
     *
     * `BusinessClock::today()` dan bukan `now()` mentah: batas harinya harus
     * hari toko, sama dengan seluruh laporan lain ([BL-082]).
     *
     * @return Builder<ProductVariant>
     */
    public function expiredOnShelf(Tenant $tenant): Builder
    {
        return ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $tenant->id))
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', BusinessClock::today())
            ->where('stock', '>', 0);
    }
}
