<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Menerjemahkan cara berjualan menjadi setelan awal sebuah tenant.
 *
 * Satu-satunya pembaca `config('business-presets')`. Alasannya bukan kerapian
 * melainkan batas yang gampang bocor: preset ini NILAI AWAL, dipakai sekali
 * saat pendaftaran, dan tidak boleh diterapkan ulang saat pemilik membetulkan
 * jenis usahanya dari Pengaturan (`[BL-034]`). Selama hanya satu kelas yang
 * tahu cara membacanya, "siapa saja yang menerapkan preset" bisa dijawab
 * dengan mencari pemakai kelas ini.
 *
 * Sejak `[BL-035]` pemakainya dua, dan keduanya disengaja: pendaftaran, dan
 * tombol "Terapkan paket ini" di Pengaturan. Yang kedua tidak melanggar batas
 * di atas justru karena ia DIMINTA pengguna dan memperlihatkan perubahannya
 * lebih dulu — yang dilarang adalah penerapan ulang diam-diam.
 */
class BusinessPresetService
{
    /**
     * Cara berjualan yang dikenali, beserta label dan keterangannya.
     *
     * @return list<array{name: string, label: string, description: string}>
     */
    public function styles(): array
    {
        $styles = [];

        foreach (config('business-presets.styles', []) as $name => $definition) {
            $styles[] = [
                'name' => $name,
                'label' => $definition['label'],
                'description' => $definition['description'],
            ];
        }

        return $styles;
    }

    /**
     * Nama cara berjualan yang sah — untuk aturan validasi.
     *
     * @return list<string>
     */
    public function styleNames(): array
    {
        return array_keys(config('business-presets.styles', []));
    }

    /**
     * Peta `jenis usaha → tebakan cara berjualan`, utuh, supaya formulir bisa
     * mengisi pertanyaan kedua tanpa bolak-balik ke server.
     *
     * @return array<string, string>
     */
    public function businessTypeStyles(): array
    {
        return config('business-presets.business_type_styles', []);
    }

    /**
     * Tebakan cara berjualan untuk sebuah jenis usaha.
     *
     * Jenis usaha yang tidak dikenali — termasuk `null` dari pendaftar yang
     * melewatkan pertanyaannya — jatuh ke cara berjualan bawaan, bukan ke
     * kosong. Tenant tanpa satu pun setelan lebih buruk daripada keadaan
     * sebelum paket ada.
     */
    public function styleForBusinessType(?string $businessType): string
    {
        $map = config('business-presets.business_type_styles', []);

        return $map[$businessType] ?? $this->defaultStyle();
    }

    public function defaultStyle(): string
    {
        return config('business-presets.default_style', 'lainnya');
    }

    /**
     * Isi paket untuk sebuah cara berjualan.
     *
     * @return array{features: list<string>, settings: array<string, mixed>}
     */
    public function presetFor(?string $style): array
    {
        $presets = config('business-presets.presets', []);
        $preset = $presets[$style] ?? $presets[$this->defaultStyle()] ?? [];

        return [
            'features' => array_values($preset['features'] ?? []),
            'settings' => $preset['settings'] ?? [],
        ];
    }

    /**
     * Kapabilitas boolean yang menyala untuk cara berjualan tersebut.
     *
     * @return list<string>
     */
    public function featuresFor(?string $style): array
    {
        return $this->presetFor($style)['features'];
    }

    /**
     * Setelan bernilai-pilihan untuk cara berjualan tersebut.
     *
     * @return array<string, mixed>
     */
    public function settingsFor(?string $style): array
    {
        return $this->presetFor($style)['settings'];
    }

    /**
     * Ubah daftar nama fitur yang DIKIRIM FORMULIR menjadi kolom boolean.
     *
     * Selalu menyebut setiap kolom boolean yang TAMPIL, bukan hanya yang
     * menyala: hasilnya dipakai untuk membuat tenant, dan kolom yang tidak
     * disebut akan diam-diam mengambil bawaan `$attributes` — yang berarti
     * melepas centang di formulir tidak selalu berarti mati (`ai_enabled`
     * bawaannya menyala).
     *
     * Yang TIDAK tampil sengaja tidak ikut. Formulir tidak pernah menawarkannya,
     * jadi memperlakukan ketidakhadirannya sebagai "dilepas centangnya" akan
     * memaksa mati setiap setelan tersembunyi yang paketnya ingin nyalakan.
     * Nilainya datang dari `presetColumnsFor()`.
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

        foreach ($this->settingsOfType('boolean') as $name => $definition) {
            if ($definition['visible'] ?? false) {
                $columns[$definition['column']] = in_array($name, $features, true);
            }
        }

        return $columns;
    }

    /**
     * Seluruh kolom yang ditentukan sebuah paket — boolean maupun pilihan,
     * tampil maupun tersembunyi.
     *
     * Inilah dasar yang dipakai dua pemakai kelas ini. Pendaftaran menimpanya
     * dengan jawaban formulir; tombol "Terapkan paket ini" memakainya apa
     * adanya.
     *
     * @return array<string, mixed>
     */
    public function presetColumnsFor(?string $style): array
    {
        $preset = $this->presetFor($style);
        $columns = [];

        foreach ($this->settingsOfType('boolean') as $name => $definition) {
            $columns[$definition['column']] = in_array($name, $preset['features'], true);
        }

        return [...$columns, ...$this->settingColumnsFor($preset['settings'])];
    }

    /**
     * Ubah peta `nama setelan → nilai` menjadi kolom yang siap disimpan.
     *
     * Kebalikan dari `columnsFor()`: yang TIDAK disebut sengaja tidak muncul di
     * hasil, supaya setelan yang tak diatur paket tetap memakai bawaan
     * kolomnya, bukan ditimpa `null`.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function settingColumnsFor(array $settings): array
    {
        $columns = [];
        $catalog = config('business-presets.settings', []);

        foreach ($settings as $name => $value) {
            if (isset($catalog[$name])) {
                $columns[$catalog[$name]['column']] = $value;
            }
        }

        return $columns;
    }

    /**
     * Nama fitur yang boleh dikirim formulir — untuk aturan validasi.
     *
     * Hanya yang TAMPIL. Setelan tersembunyi tidak pernah ditawarkan layar,
     * jadi menerimanya di sini berarti membuka jalur agar permintaan buatan
     * tangan menyetel sesuatu yang tidak pernah ada tombolnya.
     *
     * @return list<string>
     */
    public function featureNames(): array
    {
        return array_keys(array_filter(
            $this->settingsOfType('boolean'),
            static fn (array $definition): bool => $definition['visible'] ?? false
        ));
    }

    /**
     * Katalog untuk layar pendaftaran: satu baris per kapabilitas boolean yang
     * TAMPIL, dengan keterangan yang menjelaskan apa yang berubah bila
     * dicentang.
     *
     * @return list<array{name: string, label: string, description: string}>
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach ($this->settingsOfType('boolean') as $name => $definition) {
            if ($definition['visible'] ?? false) {
                $catalog[] = [
                    'name' => $name,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                ];
            }
        }

        return $catalog;
    }

    /**
     * Setelan yang TIDAK tampil di formulir, sudah diterjemahkan jadi kalimat
     * untuk ringkasan.
     *
     * Inilah yang menegakkan syarat pembalikan aturan di
     * `config/business-presets.php`: setelan boleh mendarat tanpa ditanyakan,
     * tapi tidak boleh mendarat tanpa disebutkan. Kalau daftar ini berhenti
     * ditampilkan, syaratnya batal.
     *
     * @return list<array{name: string, label: string, value: string}>
     */
    public function hiddenSummaryFor(?string $style): array
    {
        $preset = $this->presetFor($style);
        $summary = [];

        foreach (config('business-presets.settings', []) as $name => $definition) {
            if ($definition['visible'] ?? false) {
                continue;
            }

            $value = $definition['type'] === 'boolean'
                ? in_array($name, $preset['features'], true)
                : ($preset['settings'][$name] ?? null);

            $summary[] = [
                'name' => $name,
                'label' => $definition['label'],
                'value' => $this->describeValue($name, $definition, $value),
            ];
        }

        return $summary;
    }

    /**
     * Ringkasan setelan tersembunyi untuk SETIAP cara berjualan, supaya layar
     * bisa menggantinya seketika saat pilihannya diubah.
     *
     * @return array<string, list<array{name: string, label: string, value: string}>>
     */
    public function hiddenSummaries(): array
    {
        $summaries = [];

        foreach (array_keys(config('business-presets.presets', [])) as $style) {
            $summaries[$style] = $this->hiddenSummaryFor($style);
        }

        return $summaries;
    }

    /**
     * Seluruh peta cara berjualan → isi paketnya, supaya formulir bisa mengisi
     * ulang daftar centangnya tanpa bolak-balik ke server.
     *
     * @return array<string, array{features: list<string>, settings: array<string, mixed>}>
     */
    public function presets(): array
    {
        $presets = [];

        foreach (array_keys(config('business-presets.presets', [])) as $style) {
            $presets[$style] = $this->presetFor($style);
        }

        return $presets;
    }

    /**
     * Nilai setelan bertipe `choice` yang sah, per nama setelan.
     *
     * Sumbernya `Tenant` sendiri, bukan daftar kedua di config: pilihan yang
     * tampil di layar tidak boleh menyimpang dari yang diterima server, dan
     * dua daftar yang berselisih hanya terlihat sebagai validasi yang menolak
     * pilihan yang baru saja ditawarkan.
     *
     * @return array<string, array<string, string>>
     */
    public function choiceOptions(): array
    {
        return [
            'order_identity_mode' => Tenant::orderIdentityModes(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function settingsOfType(string $type): array
    {
        return array_filter(
            config('business-presets.settings', []),
            static fn (array $definition): bool => ($definition['type'] ?? 'boolean') === $type
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function describeValue(string $name, array $definition, mixed $value): string
    {
        if (($definition['type'] ?? 'boolean') === 'boolean') {
            return $value ? 'Menyala' : 'Mati';
        }

        if ($value === null) {
            return 'Bawaan';
        }

        return $this->choiceOptions()[$name][$value] ?? (string) $value;
    }
}
