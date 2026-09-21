<?php

namespace App\Services\Upsell;

use App\Models\UpsellEvent;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Satu saran upsell, siap dikirim ke POS atau ke consumer self-order.
 *
 * Skornya dihitung di server dan ikut di sini supaya client cukup menyortir —
 * ia tidak perlu tahu apa pun soal kedaluwarsa, ko-okurensi, atau selisih harga.
 *
 * @implements Arrayable<string, mixed>
 */
final class Suggestion implements Arrayable
{
    /**
     * Saran berdiskon didahulukan, tapi hanya DI DALAM kelompoknya sendiri.
     *
     * Pelanggan lebih mudah mengiyakan barang yang sedang dipotong harganya, dan
     * sejak jatah tawaran per transaksi benar-benar membatasi jumlah tawaran,
     * slot yang sedikit itu sebaiknya diisi yang paling mungkin diterima.
     *
     * Dua kelompok, dua besaran, karena rentang skornya berbeda:
     *
     *   - Mesin (0–100): +200. Saran mesin berdiskon (200–300) melampaui semua
     *     saran mesin tanpa diskon, dan tetap jauh di bawah lantai aturan owner.
     *   - Aturan owner (1000 + prioritas 0–999): +1000. Aturan berdiskon
     *     melampaui semua aturan tanpa diskon; di antara sesamanya, prioritas
     *     owner tetap yang menentukan.
     *
     * Satu besaran untuk keduanya tidak bisa: +1000 pada saran mesin menembus
     * lantai aturan owner, dan janji `ManualRuleStrategy` — saran yang dipasang
     * owner tidak tergeser mesin — ingkar diam-diam.
     */
    private const DISCOUNT_BOOST_MACHINE = 200.0;

    private const DISCOUNT_BOOST_MANUAL = 1000.0;

    /**
     * @param  float  $score  skor DASAR dari strategi; urutan yang dipakai adalah `rankScore()`
     * @param  ?float  $regularPrice  harga KATALOG varian yang disarankan, pembanding
     *                                `suggestedVariantPrice` untuk tahu apakah ia sedang berdiskon
     */
    public function __construct(
        public readonly string $type,
        public readonly string $reason,
        public readonly string $label,
        public readonly string $note,
        public readonly float $extraAmount,
        public readonly float $score,
        public readonly ?int $triggerVariantId = null,
        public readonly ?int $suggestedVariantId = null,
        public readonly ?int $suggestedModifierId = null,
        public readonly ?string $suggestedVariantName = null,
        public readonly ?float $suggestedVariantPrice = null,
        public readonly ?float $regularPrice = null,
    ) {}

    /**
     * Identitas stabil satu saran — dipakai client untuk mengingat mana yang
     * sudah ditutup kasir, dan untuk mencocokkan event saat checkout.
     */
    public function key(): string
    {
        return self::keyFor(
            $this->type,
            $this->triggerVariantId,
            $this->suggestedVariantId,
            $this->suggestedModifierId,
        );
    }

    /**
     * Kunci yang SAMA, dihitung tanpa perlu merakit sarannya lebih dulu.
     *
     * Ada karena `RuleOutcomeResolver` harus mencari tahu saran mana di dalam
     * indeks yang berasal dari sebuah `UpsellRule` — dan ia memegang aturannya,
     * bukan sarannya. Sebelum ini ada, satu-satunya jalan adalah menyalin
     * susunan kunci di atas ke tempat kedua, lalu berharap keduanya ikut
     * berubah bersamaan saat susunannya diubah. Kunci yang menyimpang di satu
     * tempat tidak melempar error apa pun: ia hanya berhenti menemukan
     * pasangannya, dan seluruh aturan owner mendadak berstatus "tidak ikut
     * perebutan slot" tanpa sebab yang terlihat.
     */
    public static function keyFor(
        string $type,
        ?int $triggerVariantId,
        ?int $suggestedVariantId,
        ?int $suggestedModifierId = null,
    ): string {
        return implode(':', [
            $type,
            $triggerVariantId ?? 'cart',
            $suggestedModifierId !== null ? 'm'.$suggestedModifierId : 'v'.$suggestedVariantId,
        ]);
    }

    /**
     * Varian yang disarankan sedang dijual di bawah harga katalognya.
     *
     * Add-on selalu false: potongan dinamis melekat pada varian, bukan modifier.
     */
    public function isDiscounted(): bool
    {
        return $this->suggestedVariantPrice !== null
            && $this->regularPrice !== null
            && $this->suggestedVariantPrice < $this->regularPrice;
    }

    /**
     * Skor yang dipakai MENGURUTKAN — skor dasar ditambah prioritas diskon.
     *
     * Semua jalur yang berebut slot memakai angka ini, lewat `score` di
     * `toArray()`: kasir, pratinjau owner, dan self-order. Satu tempat menghitung,
     * supaya ketiganya tidak pernah berselisih soal saran mana yang didahulukan.
     */
    public function rankScore(): float
    {
        if (! $this->isDiscounted()) {
            return $this->score;
        }

        return $this->score + ($this->type === UpsellEvent::TYPE_MANUAL
            ? self::DISCOUNT_BOOST_MANUAL
            : self::DISCOUNT_BOOST_MACHINE);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'type' => $this->type,
            'reason' => $this->reason,
            'label' => $this->label,
            'note' => $this->note,
            'extra_amount' => round($this->extraAmount, 2),
            'score' => round($this->rankScore(), 2),
            'discounted' => $this->isDiscounted(),
            'trigger_variant_id' => $this->triggerVariantId,
            'suggested_variant_id' => $this->suggestedVariantId,
            'suggested_modifier_id' => $this->suggestedModifierId,
            'suggested_variant_name' => $this->suggestedVariantName,
            'suggested_variant_price' => $this->suggestedVariantPrice !== null
                ? round($this->suggestedVariantPrice, 2)
                : null,
            'suggested_variant_regular_price' => $this->regularPrice !== null
                ? round($this->regularPrice, 2)
                : null,
        ];
    }

    /**
     * Apakah saran ini menukar varian di keranjang (naik ukuran), bukan
     * menambah baris baru? Client memakainya untuk memilih aksi.
     */
    public function replacesTrigger(): bool
    {
        return $this->type === UpsellEvent::TYPE_UPSIZE;
    }
}
