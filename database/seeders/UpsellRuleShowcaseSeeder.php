<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellRule;
use Illuminate\Database\Seeder;

/**
 * Data peraga untuk halaman Aturan Saran Jual.
 *
 * Halaman itu punya sepuluh keadaan dan sebuah toko yang sehat hanya pernah
 * memperlihatkan satu: "Tampil". Sembilan sisanya baru muncul ketika ada yang
 * salah, jadi tanpa peraga tidak ada cara memvalidasinya selain merusak
 * katalog sungguhan dan menunggu.
 *
 * TIGA ATURAN YANG MEMBUAT SEEDER INI AMAN DIJALANKAN DI TOKO DEMO:
 *
 *   1. **Bisa dicabut utuh.** Semua yang dibuatnya bertanda — SKU berawalan
 *      `DEMO-UPSELL-` dan catatan aturan berawalan `[peraga]`. Menjalankannya
 *      lagi menghapus yang lama lebih dulu, jadi ia tidak pernah menumpuk.
 *      `--class=UpsellRuleShowcaseSeeder` dengan `PERAGA=bersih` mencabutnya
 *      tanpa memasang apa pun.
 *
 *   2. **Tidak menyentuh katalog yang sudah ada.** Tiga keadaan rusak
 *      (stok nol, kedaluwarsa, produk nonaktif) butuh barang yang rusak, dan
 *      merusak produk sungguhan akan merembet ke halaman Stok, laporan, dan
 *      kasir. Seeder ini membuat produknya sendiri.
 *
 *   3. **Tak satu pun produk peraga bisa DISARANKAN ke pelanggan.** Ketiganya
 *      rusak dengan cara yang membuat `SellableVariantQuery` membuangnya —
 *      stok nol, kedaluwarsa, nonaktif — jadi strip saran tidak akan pernah
 *      menawarkannya. Itu bukan kebetulan yang beruntung, itu alasan ketiganya
 *      dipilih: keadaan yang ingin diperagakan dan keadaan yang menjauhkannya
 *      dari pelanggan adalah keadaan yang sama.
 *
 *      **Tapi dua di antaranya TETAP TERLIHAT di grid katalog kasir** — yang
 *      stok nol muncul berlencana "Habis", yang kedaluwarsa muncul biasa saja.
 *      Hanya yang produknya nonaktif yang benar-benar hilang dari layar kasir.
 *      Ini disebutkan apa adanya karena peraga yang mengaku tidak terlihat
 *      padahal terlihat akan membuat orang mencari bug yang tidak ada.
 */
class UpsellRuleShowcaseSeeder extends Seeder
{
    private const SKU_PREFIX = 'DEMO-UPSELL-';

    private const NOTE_PREFIX = '[peraga]';

    public function run(): void
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            $this->command?->error('Tidak ada tenant. Jalankan DatabaseSeeder lebih dulu.');

            return;
        }

        $this->purge($tenant);

        if (strtolower((string) env('PERAGA')) === 'bersih') {
            $this->command?->info("Data peraga saran jual dicabut dari {$tenant->name}.");

            return;
        }

        $broken = $this->brokenVariants($tenant);
        $real = $this->realVariants($tenant);

        if ($real->count() < 4) {
            $this->command?->error('Katalog tenant ini kurang dari 4 varian sehat; peraga butuh itu untuk memperagakan perebutan slot.');

            return;
        }

        $this->seedRules($tenant, $real, $broken);

        $this->command?->info("Aturan peraga dipasang di {$tenant->name}. Buka Owner → Aturan Saran Jual.");
        $this->report($tenant);
        $this->command?->line('Mencabutnya: PERAGA=bersih php artisan db:seed --class=UpsellRuleShowcaseSeeder');
    }

    /**
     * Perlihatkan keadaan yang BENAR-BENAR terbentuk, bukan yang diniatkan.
     *
     * Dihitung lewat resolver yang sama dengan layarnya, jadi keluaran ini
     * adalah bukti, bukan klaim — versi pertama seeder ini mengaku memasang
     * sepuluh keadaan sementara satu di antaranya diam-diam tidak terbentuk.
     */
    private function report(Tenant $tenant): void
    {
        $outcomes = app(\App\Services\Upsell\RuleOutcomeResolver::class)->resolve(
            $tenant,
            app(\App\Services\Upsell\UpsellIndexBuilder::class)->build($tenant),
        );

        $rows = [];

        foreach ($outcomes as $outcome) {
            $rows[] = [$outcome['state'], $outcome['status'], $outcome['needs_attention'] ? 'ya' : '—', $outcome['label']];
        }

        $this->command?->table(['Keadaan', 'Lencana di tabel', 'Masuk daftar "kenapa"', 'Barang'], $rows);
    }

    /**
     * Tenant sasaran — `PERAGA_TENANT` bila disebut, selain itu yang pertama.
     */
    private function tenant(): ?Tenant
    {
        $slug = env('PERAGA_TENANT');

        return $slug !== null
            ? Tenant::where('slug', $slug)->first()
            : Tenant::orderBy('id')->first();
    }

    /**
     * Cabut jejak peraga sebelumnya — aturannya lebih dulu, baru produknya,
     * supaya tidak ada aturan yang sesaat menunjuk varian yang sudah hilang.
     */
    private function purge(Tenant $tenant): void
    {
        UpsellRule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('note', 'like', self::NOTE_PREFIX.'%')
            ->delete();

        $products = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereHas('variants', fn ($query) => $query->where('sku', 'like', self::SKU_PREFIX.'%'))
            ->get();

        foreach ($products as $product) {
            // Aturan mana pun yang menunjuk varian ini ikut dicabut, termasuk
            // aturan yang ditulis tangan saat memvalidasi — meninggalkannya
            // akan membuat halaman berisi "Barangnya hilang" yang tidak
            // diminta siapa pun.
            $variantIds = $product->variants()->pluck('id');

            UpsellRule::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where(fn ($query) => $query->whereIn('suggested_variant_id', $variantIds)
                    ->orWhereIn('trigger_variant_id', $variantIds))
                ->delete();

            $product->variants()->delete();
            $product->forceDelete();
        }
    }

    /**
     * Tiga barang yang sengaja rusak, satu per sebab yang tidak bisa
     * diperagakan dengan katalog yang sehat.
     *
     * @return array<string, ProductVariant>
     */
    private function brokenVariants(Tenant $tenant): array
    {
        return [
            'out_of_stock' => $this->makeVariant($tenant, 'Kue Sus (peraga stok habis)', 'STOK', [
                'stock' => 0,
            ]),
            'expired' => $this->makeVariant($tenant, 'Roti Sobek (peraga kedaluwarsa)', 'EXP', [
                'stock' => 12,
                'expiry_date' => now()->subDays(3)->toDateString(),
            ]),
            'product_inactive' => $this->makeVariant($tenant, 'Es Kopi Arsip (peraga nonaktif)', 'OFF', [
                'stock' => 30,
            ], productActive: false),
        ];
    }

    /**
     * @param  array<string, mixed>  $variantAttributes
     */
    private function makeVariant(
        Tenant $tenant,
        string $productName,
        string $marker,
        array $variantAttributes,
        bool $productActive = true,
    ): ProductVariant {
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $this->categoryId($tenant),
            'name' => $productName,
            'is_active' => $productActive,
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Porsi',
            'sku' => self::SKU_PREFIX.$marker,
            'price' => 15000,
            'cost_price' => 6000,
            ...$variantAttributes,
        ]);
    }

    private function categoryId(Tenant $tenant): ?int
    {
        return \App\Models\Category::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->value('id');
    }

    /**
     * Varian katalog yang benar-benar bisa dijual — dipakai untuk keadaan yang
     * tidak menuntut kerusakan apa pun.
     *
     * @return \Illuminate\Support\Collection<int, ProductVariant>
     */
    private function realVariants(Tenant $tenant): \Illuminate\Support\Collection
    {
        // Penjaga kandidat ditiru UTUH, termasuk kedaluwarsa. Versi pertama
        // seeder ini hanya menyaring stok dan produk aktif, lalu memilih varian
        // yang ternyata sudah kedaluwarsa sebagai salah satu dari empat
        // penantang slot — sehingga penantangnya tinggal tiga, ketiganya menang,
        // dan "Kalah slot" tidak pernah terbentuk. Peraga yang diam-diam gagal
        // memperagakan satu keadaan lebih buruk daripada tidak ada peraga.
        return ProductVariant::withoutGlobalScopes()
            ->where('stock', '>', 0)
            ->where(fn ($query) => $query->whereNull('expiry_date')
                ->orWhere('expiry_date', '>=', now()->startOfDay()))
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $tenant->id)
                ->where('is_active', true))
            ->whereNull('sku')
            ->orderBy('id')
            ->get();
    }

    /**
     * Sepuluh aturan, satu per keadaan yang bisa dilaporkan halaman itu.
     *
     * @param  \Illuminate\Support\Collection<int, ProductVariant>  $real
     * @param  array<string, ProductVariant>  $broken
     */
    private function seedRules(Tenant $tenant, \Illuminate\Support\Collection $real, array $broken): void
    {
        // Empat aturan tanpa pemicu yang seluruhnya sehat, sementara kasir
        // hanya punya tiga slot. Prioritas menaik, jadi yang terbawah PASTI
        // tergeser — itulah satu-satunya cara memperagakan "Kalah slot" tanpa
        // menunggu kebetulan.
        $contenders = $real->take(4)->values();

        foreach ([90, 80, 70, 60] as $position => $priority) {
            $this->rule($tenant, $contenders[$position], [
                'note' => self::NOTE_PREFIX.' Dorong '.$contenders[$position]->name,
                'priority' => $priority,
            ]);
        }

        // Berpemicu dan sehat: yang dicari owner saat bertanya "aturan saya
        // muncul di mana".
        //
        // Barang yang disarankan sengaja diambil DI LUAR keempat penantang di
        // atas. Satu barang hanya boleh mengisi satu slot ([BL-101]), jadi
        // menyarankan barang yang sudah didorong aturan tanpa-pemicu akan
        // membuat aturan ini berstatus "Diwakili saran lain" — benar, tapi
        // bukan keadaan yang sedang ingin diperagakan di sini.
        $triggered = $real->slice(4)->first() ?? $real->last();

        // Prioritasnya DI ATAS keempat penantang, dan itu perlu: saran
        // tanpa-pemicu ikut berebut di setiap pita berpemicu, jadi aturan
        // berpemicu berprioritas rendah akan tergeser oleh mereka dan
        // memperagakan "Kalah slot" untuk kedua kalinya alih-alih "Tampil".
        $this->rule($tenant, $triggered, [
            'trigger_variant_id' => $contenders->first()->id,
            'note' => self::NOTE_PREFIX.' Tawarkan saat barang pemicu dibeli',
            'priority' => 95,
        ]);

        // Dua aturan mendorong barang yang sama — kesalahan yang wajar, dan
        // yang paling membingungkan tanpa keterangan: aturannya sehat, lolos
        // semua penjagaan, tapi tetap tidak menambah apa pun karena satu barang
        // hanya boleh mengisi satu slot ([BL-101]).
        $this->rule($tenant, $contenders->get(2), [
            'trigger_variant_id' => $contenders->get(1)->id,
            'note' => self::NOTE_PREFIX.' Dorong barang yang sudah didorong aturan lain',
            'priority' => 10,
        ]);

        // Tiga sebab yang menuntut barang rusak.
        foreach (['out_of_stock', 'expired', 'product_inactive'] as $state) {
            $this->rule($tenant, $broken[$state], [
                'note' => self::NOTE_PREFIX.' Uji sebab: '.$state,
                'priority' => 50,
            ]);
        }

        // Pemicunya yang rusak, bukan barang yang disarankan — sebab yang
        // paling mudah terlewat, karena barang yang disarankan tampak sehat.
        $this->rule($tenant, $real->first(), [
            'trigger_variant_id' => $broken['product_inactive']->id,
            'note' => self::NOTE_PREFIX.' Uji sebab: pemicu nonaktif',
            'priority' => 45,
        ]);

        // Dua ujung jendela tanggal dan saklar owner.
        $this->rule($tenant, $real->get(2) ?? $real->first(), [
            'note' => self::NOTE_PREFIX.' Promo yang belum mulai',
            'starts_on' => now()->addWeek()->toDateString(),
            'priority' => 40,
        ]);

        $this->rule($tenant, $real->get(3) ?? $real->first(), [
            'note' => self::NOTE_PREFIX.' Promo yang sudah lewat',
            'ends_on' => now()->subWeek()->toDateString(),
            'priority' => 35,
        ]);

        $this->rule($tenant, $real->get(4) ?? $real->first(), [
            'note' => self::NOTE_PREFIX.' Aturan yang saya matikan sendiri',
            'is_active' => false,
            'priority' => 30,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(Tenant $tenant, ProductVariant $suggested, array $attributes): void
    {
        UpsellRule::create([
            'tenant_id' => $tenant->id,
            'trigger_variant_id' => null,
            'suggested_variant_id' => $suggested->id,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
