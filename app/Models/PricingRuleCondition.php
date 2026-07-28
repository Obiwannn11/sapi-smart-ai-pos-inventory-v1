<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu syarat dari sebuah aturan harga: "dimensi ini, dibandingkan begini,
 * dengan nilai itu".
 *
 * Sebuah aturan cocok hanya bila SELURUH syaratnya terpenuhi. Tidak ada bentuk
 * "atau" di sini, dan itu disengaja: aturan ber-"atau" selalu bisa ditulis
 * sebagai dua aturan terpisah, sedangkan menyediakan "atau" membuat pertanyaan
 * "kenapa tenant ini dapat harga segini" jauh lebih sulit dijawab belakangan.
 */
class PricingRuleCondition extends Model
{
    /** @use HasFactory<\Database\Factories\PricingRuleConditionFactory> */
    use HasFactory;

    /** Lebih besar atau sama dengan — batas bawah rentang. */
    public const OP_GTE = 'gte';

    /** Lebih besar dari. */
    public const OP_GT = 'gt';

    /** Lebih kecil atau sama dengan. */
    public const OP_LTE = 'lte';

    /** Lebih kecil dari — batas atas rentang, sengaja eksklusif. */
    public const OP_LT = 'lt';

    /** Sama dengan. */
    public const OP_EQ = 'eq';

    /** Tidak sama dengan. */
    public const OP_NEQ = 'neq';

    /** Termasuk salah satu dari daftar yang dipisah koma. */
    public const OP_IN = 'in';

    protected $fillable = ['pricing_rule_id', 'dimension', 'operator', 'value'];

    // --- Relationships ---
    public function rule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class, 'pricing_rule_id');
    }

    // --- Helpers ---

    /**
     * Operator yang masuk akal untuk sebuah tipe dimensi.
     *
     * Membandingkan tipe usaha dengan "lebih besar dari" tidak punya arti, dan
     * membiarkannya tersedia di panel hanya melahirkan aturan yang tak pernah
     * cocok tanpa memberi tahu siapa pun kenapa.
     *
     * @return list<string>
     */
    public static function operatorsForType(string $type): array
    {
        return $type === 'attribute'
            ? [self::OP_EQ, self::OP_NEQ, self::OP_IN]
            : [self::OP_GTE, self::OP_GT, self::OP_LTE, self::OP_LT, self::OP_EQ];
    }

    /**
     * @return list<string>
     */
    public static function allOperators(): array
    {
        return [self::OP_GTE, self::OP_GT, self::OP_LTE, self::OP_LT, self::OP_EQ, self::OP_NEQ, self::OP_IN];
    }

    /**
     * Apakah nilai yang sudah diselesaikan memenuhi syarat ini.
     *
     * `null` SELALU berarti tidak terpenuhi — dan itulah aturan gagal-menutup
     * yang menopang seluruh mekanisme ini. Nilai null muncul ketika dimensinya
     * tak bisa dihitung: tenant belum menyetujui pembukaan datanya, ringkasan
     * omsetnya belum pernah dihitung, atau tipe usahanya tak pernah ditanyakan.
     * Memperlakukannya sebagai "lolos" akan membuat aturan bersyarat justru
     * berlaku paling luas bagi tenant yang datanya paling sedikit diketahui.
     */
    public function isSatisfiedBy(float|string|null $resolved): bool
    {
        if ($resolved === null) {
            return false;
        }

        if ($this->operator === self::OP_IN) {
            $diizinkan = array_map('trim', explode(',', $this->value));

            return in_array((string) $resolved, $diizinkan, true);
        }

        if ($this->operator === self::OP_EQ || $this->operator === self::OP_NEQ) {
            // Dibandingkan sebagai angka bila keduanya angka, supaya "5" dan
            // "5.0" tidak dianggap berbeda; selain itu sebagai teks.
            $sama = is_numeric($resolved) && is_numeric($this->value)
                ? (float) $resolved === (float) $this->value
                : (string) $resolved === $this->value;

            return $this->operator === self::OP_EQ ? $sama : ! $sama;
        }

        // Sisanya perbandingan berurut, dan hanya angka yang punya urutan.
        if (! is_numeric($resolved) || ! is_numeric($this->value)) {
            return false;
        }

        $kiri = (float) $resolved;
        $kanan = (float) $this->value;

        return match ($this->operator) {
            self::OP_GTE => $kiri >= $kanan,
            self::OP_GT => $kiri > $kanan,
            self::OP_LTE => $kiri <= $kanan,
            self::OP_LT => $kiri < $kanan,
            // Operator tak dikenal ikut gagal menutup. Baris rusak di database
            // tidak boleh berubah jadi aturan yang cocok untuk semua orang.
            default => false,
        };
    }
}
