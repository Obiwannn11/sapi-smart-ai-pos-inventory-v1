<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu varian yang melewati tanggal kedaluwarsanya dengan stok tersisa.
 *
 * Ditulis sekali oleh `ExpiredStockRecorder` dan tidak pernah diubah sesudahnya
 * — ia catatan pengamatan, bukan keadaan. Kalau barangnya kemudian dibuang atau
 * stoknya dikoreksi, barisnya TETAP: yang dicatat adalah apa yang ada di rak
 * pagi itu, dan itu tidak berubah hanya karena raknya sekarang kosong.
 */
class ExpiredStockRecord extends Model
{
    use BelongsToTenant, HasFactory;

    /** Diamati pencatat harian pada pagi sesudah barangnya basi. */
    public const SOURCE_RECORDER = 'recorder';

    /**
     * Sudah basi sebelum pencatatnya ada; `qty` NULL karena tidak diketahui.
     *
     * Baris ini ada untuk MENAHAN pencatat, bukan untuk dihitung. Pembaca angka
     * periode wajib menyaringnya keluar — lihat {@see self::scopeMeasured()}.
     */
    public const SOURCE_PRE_EXISTING = 'pre_existing';

    protected $fillable = [
        'tenant_id',
        'product_variant_id',
        'label',
        'expiry_date',
        'recorded_on',
        'qty',
        'cost_price',
        'value',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'recorded_on' => 'date',
            'cost_price' => 'decimal:2',
            'value' => 'decimal:2',
        ];
    }

    /**
     * Hanya baris yang jumlahnya benar-benar diukur.
     *
     * Setiap angka rupiah yang ditampilkan ke pemilik wajib lewat sini. Baris
     * `pre_existing` punya `value` NULL, jadi SUM-nya kebetulan tidak rusak —
     * tapi COUNT-nya rusak, dan "12 varian basi bulan ini" yang diam-diam
     * memuat barang basi tahun lalu adalah persis jenis angka yang [BL-105]
     * ditulis untuk dicegah.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ExpiredStockRecord>  $query
     */
    public function scopeMeasured($query): void
    {
        $query->where('source', self::SOURCE_RECORDER);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
