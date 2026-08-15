<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Menerjemahkan jenis usaha menjadi kapabilitas awal sebuah tenant.
 *
 * Satu-satunya pembaca `config('business-presets')`. Alasannya bukan kerapian
 * melainkan batas yang gampang bocor: preset ini NILAI AWAL, dipakai sekali
 * saat pendaftaran, dan tidak boleh diterapkan ulang saat pemilik membetulkan
 * jenis usahanya dari Pengaturan (`[BL-034]`). Selama hanya satu kelas yang
 * tahu cara membacanya, "siapa saja yang menerapkan preset" bisa dijawab
 * dengan mencari pemakai kelas ini — dan jawabannya hari ini satu, yaitu
 * pendaftaran.
 */
class BusinessPresetService
{
    /**
     * Kapabilitas yang menyala untuk jenis usaha tersebut.
     *
     * Jenis usaha yang tidak dikenali — termasuk `null` dari pendaftar yang
     * melewatkan pertanyaannya — jatuh ke preset bawaan, bukan ke daftar
     * kosong. Tenant tanpa satu pun kapabilitas lebih buruk daripada keadaan
     * sebelum preset ada.
     *
     * @return list<string>
     */
    public function featuresFor(?string $businessType): array
    {
        $presets = config('business-presets.presets', []);

        return array_values(
            $presets[$businessType]
                ?? $presets[Tenant::BUSINESS_TYPE_DEFAULT]
                ?? []
        );
    }

    /**
     * Ubah daftar nama fitur menjadi kolom `*_enabled` yang siap disimpan.
     *
     * Selalu menyebut SETIAP kolom, bukan hanya yang menyala: hasilnya dipakai
     * untuk membuat tenant, dan kolom yang tidak disebut akan diam-diam
     * mengambil bawaan `$attributes` — yang berarti melepas centang di formulir
     * tidak selalu berarti mati (`ai_enabled` bawaannya menyala).
     *
     * Nama fitur asing diabaikan, tidak melempar: validasi request yang
     * menolaknya, dan kelas ini tidak boleh jadi pintu kedua yang bisa
     * menjatuhkan pendaftaran.
     *
     * @param  list<string>  $features
     * @return array<string, bool>
     */
    public function columnsFor(array $features): array
    {
        $columns = [];

        foreach (config('business-presets.features', []) as $name => $definition) {
            $columns[$definition['column']] = in_array($name, $features, true);
        }

        return $columns;
    }

    /**
     * Nama fitur yang sah — untuk aturan validasi.
     *
     * @return list<string>
     */
    public function featureNames(): array
    {
        return array_keys(config('business-presets.features', []));
    }

    /**
     * Katalog untuk layar pendaftaran: satu baris per kapabilitas, dengan
     * keterangan yang menjelaskan apa yang berubah bila dicentang.
     *
     * @return list<array{name: string, label: string, description: string}>
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach (config('business-presets.features', []) as $name => $definition) {
            $catalog[] = [
                'name' => $name,
                'label' => $definition['label'],
                'description' => $definition['description'],
            ];
        }

        return $catalog;
    }

    /**
     * Seluruh peta jenis usaha → fitur, supaya formulir bisa mengisi ulang
     * daftar centangnya tanpa bolak-balik ke server.
     *
     * @return array<string, list<string>>
     */
    public function presets(): array
    {
        return array_map(
            static fn (array $features): array => array_values($features),
            config('business-presets.presets', [])
        );
    }
}
