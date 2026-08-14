<?php

namespace App\Services\Pricing;

use App\Models\PricingRuleCondition;
use App\Models\Tenant;
use App\Services\ConsentService;
use Illuminate\Support\Carbon;

/**
 * Pintu tunggal menuju nilai dimensi harga.
 *
 * Dua tugasnya tidak boleh dipisah, dan itu alasan kelas ini ada: menerjemahkan
 * nama dimensi jadi angkanya, DAN menolak menerjemahkan yang belum disetujui
 * tenant. Kalau pemanggil boleh memanggil resolver langsung, penjaga consent-nya
 * berubah jadi kesepakatan tak tertulis yang cukup dilupakan sekali.
 */
class DimensionRegistry
{
    public function __construct(private readonly ConsentService $consents) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function catalog(): array
    {
        return config('pricing-dimensions', []);
    }

    public function has(string $dimension): bool
    {
        return array_key_exists($dimension, $this->catalog());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function definition(string $dimension): ?array
    {
        return $this->catalog()[$dimension] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->catalog());
    }

    /**
     * Nilai satu dimensi bagi tenant, atau `null` bila tidak boleh/tidak bisa
     * ditentukan.
     *
     * Pemanggil TIDAK diberi tahu bedanya "belum disetujui" dan "belum ada
     * angkanya", dan itu disengaja: keduanya sama-sama berarti aturan yang
     * menyebutnya tidak cocok, sementara membedakannya di sini akan membuat
     * pemanggil tergoda memperlakukan salah satunya sebagai lolos.
     */
    public function valueFor(Tenant $tenant, string $dimension, ?Carbon $asOf = null): float|string|null
    {
        $definition = $this->definition($dimension);

        if ($definition === null) {
            return null;
        }

        $consent = $definition['requires_consent'] ?? null;

        // Syaratnya persetujuan yang MASIH AKTIF — pernah disetujui dan belum
        // dicabut. Sengaja BUKAN `hasAgreedToCurrent()`, dan bedanya penting:
        // pemeriksaan versi-terkini dipakai untuk memutuskan kapan tenant perlu
        // diminta menyetujui ulang, dan memakainya di sini berarti satu kali
        // menaikkan versi teks consent akan memadamkan dimensi ini bagi SELURUH
        // tenant sekaligus — harga mereka berubah diam-diam pada hari revisi
        // teks terbit, tanpa satu pun dari mereka melakukan apa-apa.
        //
        // Pencabutan tetap memadamkannya seketika, dan itu memang yang
        // dijanjikan dokumen consent.
        if ($consent !== null && $this->consents->latestFor($tenant, $consent) === null) {
            return null;
        }

        return app($definition['resolver'])->resolve($tenant, $asOf);
    }

    /**
     * Dimensi yang nilainya boleh dihitung TANPA persetujuan apa pun.
     *
     * Berdiri sendiri, bukan `array_filter` di pemanggil, karena inilah satu-
     * satunya definisi "aman dibacakan kepada tenant jalur Harga Tetap" — dan
     * definisi yang disalin ke dua tempat cepat atau lambat berbeda. Menambah
     * dimensi ber-consent baru otomatis tersaring di sini; menambah yang tanpa
     * consent otomatis ikut.
     *
     * @return list<string>
     */
    public function consentFreeNames(): array
    {
        return array_values(array_filter(
            $this->names(),
            fn (string $dimension) => ($this->definition($dimension)['requires_consent'] ?? null) === null,
        ));
    }

    /**
     * Seluruh nilai dimensi tenant, siap dicocokkan dengan syarat aturan.
     *
     * @return array<string, float|string|null>
     */
    public function contextFor(Tenant $tenant, ?Carbon $asOf = null): array
    {
        $context = [];

        foreach ($this->names() as $dimension) {
            $context[$dimension] = $this->valueFor($tenant, $dimension, $asOf);
        }

        return $context;
    }

    /**
     * Bentuk katalog untuk form panel: label, tipe, operator yang masuk akal,
     * dan pilihan nilai bila dimensinya bertipe atribut.
     *
     * Kelas resolver sengaja TIDAK ikut — panel tidak perlu tahu nama kelas PHP
     * mana pun, dan mengirimkannya hanya membocorkan bentuk dalam aplikasi ke
     * peramban tanpa guna.
     *
     * @return list<array<string, mixed>>
     */
    public function forPanel(): array
    {
        $dimensions = [];

        foreach ($this->catalog() as $name => $definition) {
            $dimensions[] = [
                'name' => $name,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'unit' => $definition['unit'],
                'operators' => PricingRuleCondition::operatorsForType($definition['type']),
                'options' => $definition['options'] ?? null,
                // Ditampilkan terus terang di panel: aturan yang memakai
                // dimensi ini tidak akan pernah cocok untuk tenant jalur
                // normal. Menyembunyikannya akan membuat pemilik SaaS menyusun
                // aturan yang diam-diam tak berlaku bagi separuh kliennya.
                'requires_consent' => $definition['requires_consent'] ?? null,
            ];
        }

        return $dimensions;
    }
}
